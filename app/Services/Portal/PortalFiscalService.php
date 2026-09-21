<?php

namespace App\Services\Portal;

use App\Contracts\EstablishmentContextInterface;
use App\Contracts\FiscalGatewayInterface;
use App\Enums\EstadoDocumentoFiscal;
use App\Enums\TipoDocumento;
use App\Models\Configuracion;
use App\Models\ConfiguracionFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Establecimiento;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Throwable;

class PortalFiscalService
{
    public const string MODO_MANUAL = 'MANUAL';
    public const string MODO_AUTOMATICO = 'AUTOMATICO';
    public const string MODO_HIBRIDO = 'HIBRIDO';

    public function __construct(
        private readonly FiscalGatewayInterface $fiscalGateway,
        private readonly EstablishmentContextInterface $establishmentContext,
        private readonly PortalFiscalValidator $fiscalValidator,
    ) {}

    /**
     * Busca una orden por su número de seguimiento o código corto.
     */
    public function buscarOrden(string $tracking): ?array
    {
        $tracking = trim($tracking);

        $relations = [
            'detalles.producto',
            'detalles.combo',
            'detalles.detallePedidoNotas.notaCocina',
            'pago',
            'mesa',
            'usuario',
            'establecimiento',
        ];

        /** @var Pedido|null $pedido */
        $pedido = Pedido::query()
            ->with($relations)
            ->where('numero_seguimiento', $tracking)
            ->first();

        if (! $pedido && is_numeric($tracking)) {
            $matches = Pedido::query()
                ->with($relations)
                ->where('codigo_corto', (int) $tracking)
                ->limit(2)
                ->get();

            // El código corto se reinicia por sucursal; no elegir una orden
            // arbitraria si hay más de una coincidencia.
            $pedido = $matches->count() === 1 ? $matches->first() : null;
        }

        if (! $pedido) {
            return null;
        }

        /** @var DocumentoFiscal|null $docFiscal */
        $docFiscal = DocumentoFiscal::query()
            ->where('pedido_id', $pedido->getKey())
            ->latest('id')
            ->first();

        $items = [];
        $itemsTextoArray = [];
        $totalCalculado = 0.0;

        foreach ($pedido->detalles as $detalle) {
            if ($detalle->estado_linea && $detalle->estado_linea->value !== 'ACTIVA') {
                continue;
            }

            $nombre = $detalle->combo?->nombre ?? $detalle->producto?->nombre ?? 'Artículo';
            $cantidad = (int) $detalle->cantidad;
            $precio = (float) $detalle->precio_unitario;
            $subtotal = $cantidad * $precio;
            $totalCalculado += $subtotal;

            $descripciones = [];
            $opcionesCombo = [];
            $notasCocina = [];

            // Extraer opciones seleccionadas de combos por slots
            $masaLinea = data_get($detalle->configuracion_producto, 'masa.nombre');
            foreach ($detalle->seleccion_combo ?? [] as $grupo) {
                foreach ($grupo['items'] ?? [] as $itemCombo) {
                    if (! empty($itemCombo['nombre']) && ! empty($itemCombo['cantidad'])) {
                        $cantidadItem = (int) $cantidad * (int) $itemCombo['cantidad'];
                        $masaItem = data_get($itemCombo, 'masa.nombre') ?: $masaLinea;
                        $detalleItem = "{$cantidadItem}x {$itemCombo['nombre']}" . ($masaItem ? " ({$masaItem})" : '');
                        $opcionesCombo[] = $detalleItem;
                        $descripciones[] = $detalleItem;
                    }
                }
            }

            if (! $detalle->combo_id && $masaLinea) {
                $descripciones[] = 'Masa: ' . $masaLinea;
            }

            // Extraer notas de cocina / preparación
            foreach ($detalle->detallePedidoNotas as $notaDetalle) {
                if ($notaDetalle->notaCocina?->nombre) {
                    $notasCocina[] = $notaDetalle->notaCocina->nombre;
                    $descripciones[] = "Nota: {$notaDetalle->notaCocina->nombre}";
                }
            }

            $descripcionTexto = implode(' | ', $descripciones);

            $items[] = [
                'cantidad' => $cantidad,
                'nombre' => $nombre,
                'descripcion' => $descripcionTexto,
                'opciones_combo' => $opcionesCombo,
                'notas_cocina' => $notasCocina,
                'precio' => number_format($precio, 2, '.', ''),
                'subtotal' => number_format($subtotal, 2, '.', ''),
            ];

            $lineaTexto = "{$cantidad}x {$nombre} ($" . number_format($subtotal, 2, '.', '') . ")";
            if (! empty($descripciones)) {
                $lineaTexto .= "\n   ↳ " . implode("\n   ↳ ", $descripciones);
            }
            $itemsTextoArray[] = $lineaTexto;
        }

        $totalFinal = $pedido->pago?->monto_recibido !== null
            ? (float) bcsub((string) $pedido->pago->monto_recibido, (string) ($pedido->pago->cambio_devuelto ?? 0), 2)
            : $totalCalculado;

        $esPlazoValido = true;
        $motivoInvalido = null;
        try {
            $this->fiscalValidator->validarPlazoFiscal($pedido);
        } catch (ValidationException $e) {
            $esPlazoValido = false;
            $motivoInvalido = collect($e->validator->errors()->all())->first() ?? 'Plazo de emisión de factura expirado.';
        }

        return [
            'id' => $pedido->getKey(),
            'tracking_number' => $pedido->numero_seguimiento,
            'codigo_corto' => $pedido->codigo_corto,
            'fecha' => $pedido->created_at?->format('d/m/Y h:i A') ?? now()->format('d/m/Y h:i A'),
            'fecha_raw' => $pedido->created_at?->toIso8601String(),
            'plazo_valido' => $esPlazoValido,
            'motivo_invalido' => $motivoInvalido,
            'requiere_identificacion_art119' => ($totalFinal >= 200.00),
            'cliente' => $docFiscal?->datos_solicitante['nombre'] ?? 'Consumidor Final',
            'establecimiento' => $pedido->establecimiento?->nombre,
            'estado_comercial' => $pedido->estado_comercial?->value,
            'estado_solicitud' => $docFiscal?->estado?->value ?? 'SIN_SOLICITUD',
            'tipo_documento' => $docFiscal?->tipo_documento?->value,
            'codigo_generacion' => $docFiscal?->codigo_generacion,
            'sello_recepcion' => $docFiscal?->sello_recepcion,
            'numero_control' => $docFiscal?->numero_control,
            'items' => $items,
            'itemsTexto' => implode("\n", $itemsTextoArray),
            'total' => number_format($totalFinal, 2, '.', ''),
        ];
    }

    /**
     * Obtiene el modo de emisión activo del portal.
     */
    public function obtenerModoEmision(?int $establecimientoId = null): string
    {
        $establecimientoId = $this->resolvePortalEstablishmentId($establecimientoId);
        $query = Configuracion::query()->where('clave', 'modo_emision_portal');

        if ($establecimientoId !== null) {
            $query->where('establecimiento_id', $establecimientoId);
        }

        $valor = $query->value('valor');

        if (is_array($valor) && isset($valor['modo'])) {
            return (string) $valor['modo'];
        }

        if (is_string($valor) && in_array(strtoupper($valor), [self::MODO_MANUAL, self::MODO_AUTOMATICO, self::MODO_HIBRIDO], true)) {
            return strtoupper($valor);
        }

        return self::MODO_AUTOMATICO;
    }

    /**
     * Guarda el modo de emisión del portal.
     */
    public function guardarModoEmision(string $modo, ?int $establecimientoId = null): string
    {
        $modo = strtoupper(trim($modo));
        if (! in_array($modo, [self::MODO_MANUAL, self::MODO_AUTOMATICO, self::MODO_HIBRIDO], true)) {
            $modo = self::MODO_AUTOMATICO;
        }

        $establecimientoId = $this->resolvePortalEstablishmentId($establecimientoId);

        Configuracion::updateOrCreate(
            [
                'establecimiento_id' => $establecimientoId,
                'clave' => 'modo_emision_portal',
            ],
            [
                'valor' => ['modo' => $modo],
            ],
        );

        return $modo;
    }

    /**
     * Procesa la solicitud enviada por un cliente desde WebFact con validaciones fiscales y candado atómico.
     */
    public function procesarSolicitudCliente(array $datos): array
    {
        // 1. Sanitizar todos los datos de entrada contra XSS e inyecciones
        $datos = $this->fiscalValidator->sanitizarDatos($datos);

        $tracking = trim((string) ($datos['trackingPOS'] ?? ''));
        if ($tracking === '') {
            return ['success' => false, 'message' => 'El número de tracking es obligatorio.'];
        }

        /** @var Pedido|null $pedido */
        $pedido = Pedido::query()
            ->with(['detalles.producto', 'detalles.combo', 'pago', 'establecimiento.configuracionFiscal'])
            ->where('numero_seguimiento', $tracking)
            ->orWhere(function (Builder $query) use ($tracking): void {
                if (is_numeric($tracking)) {
                    $query->where('codigo_corto', (int) $tracking);
                }
            })
            ->latest('id')
            ->first();

        if (! $pedido) {
            return ['success' => false, 'message' => 'No se encontró la orden solicitada. Verifica tu ticket de compra.'];
        }

        // 2. Candado Atómico contra condiciones de carrera y doble emisión
        $lock = Cache::lock("dte_portal_pedido_{$pedido->getKey()}", 15);
        if (! $lock->get()) {
            return [
                'success' => false,
                'message' => 'Existe una solicitud en procesamiento para esta orden. Por favor espera un momento.',
            ];
        }

        try {
            // 3. Verificar si ya existe documento fiscal emitido
            $docExistente = DocumentoFiscal::query()
                ->where('pedido_id', $pedido->getKey())
                ->where('estado', EstadoDocumentoFiscal::EMITIDO)
                ->first();

            if ($docExistente) {
                return [
                    'success' => true,
                    'estado' => 'EMITIDO',
                    'message' => 'Esta orden ya cuenta con factura electrónica emitida.',
                    'dte' => [
                        'codigo_generacion' => $docExistente->codigo_generacion,
                        'sello_recepcion' => $docExistente->sello_recepcion,
                        'numero_control' => $docExistente->numero_control,
                    ],
                ];
            }

            // 4. Validar plazo de facturación y período mensual IVA (Anti-Fraude y Cierre Fiscal F-07)
            $this->fiscalValidator->validarPlazoFiscal($pedido);

            // 5. Mapear tipo de documento (Exclusivamente Factura 01 o CCF 03)
            $codigoDte = (string) ($datos['tipoDTE'] ?? '01');
            $tipoDocumento = match ($codigoDte) {
                '03', 'CCF' => TipoDocumento::CCF,
                default => TipoDocumento::FACTURA,
            };

            $datosCliente = [
                'nombre' => trim((string) ($datos['nombre'] ?? 'Consumidor Final')),
                'nit' => trim((string) ($datos['nit'] ?? $datos['dui'] ?? '')),
                'nrc' => trim((string) ($datos['nrc'] ?? '')),
                'dui' => trim((string) ($datos['dui'] ?? '')),
                'email' => trim((string) ($datos['email'] ?? '')),
                'telefono' => trim((string) ($datos['telefono'] ?? '')),
                'giro' => trim((string) ($datos['giro'] ?? '')),
                'direccion' => trim((string) ($datos['direccion'] ?? '')),
                'departamento' => trim((string) ($datos['departamento'] ?? '')),
                'municipio' => trim((string) ($datos['municipio'] ?? '')),
                'tipo_dte_codigo' => $codigoDte,
            ];

            // Calcular monto total real de la orden
            $totalMonto = $pedido->pago?->monto_recibido !== null
                ? (float) bcsub((string) $pedido->pago->monto_recibido, (string) ($pedido->pago->cambio_devuelto ?? 0), 2)
                : (float) $pedido->detalles->sum(fn ($d) => ((int) $d->cantidad) * ((float) $d->precio_unitario));

            // 6. Validaciones fiscales específicas por ley
            if ($tipoDocumento === TipoDocumento::CCF) {
                $this->fiscalValidator->validarCreditoFiscalArt114($datosCliente);
            } else {
                $this->fiscalValidator->validarArticulo119($totalMonto, $datosCliente);
            }

            $modo = $this->obtenerModoEmision($pedido->establecimiento_id);

            // En modo HÍBRIDO: Facturas < $200 se emiten automáticas; Facturas >= $200 y CCF van a supervisión (PENDIENTE)
            $debeEmitirAutomatico = ($modo === self::MODO_AUTOMATICO)
                || ($modo === self::MODO_HIBRIDO && $tipoDocumento === TipoDocumento::FACTURA && $totalMonto < 200.00);

            if ($debeEmitirAutomatico) {
                return $this->emitirDteDirecto($pedido, $tipoDocumento, $datosCliente);
            }

            // Modo Manual o Híbrido supervisado: Registrar solicitud como PENDIENTE
            $docFiscal = DocumentoFiscal::updateOrCreate(
                [
                    'pedido_id' => $pedido->getKey(),
                    'tipo_documento' => $tipoDocumento,
                ],
                [
                    'estado' => EstadoDocumentoFiscal::PENDIENTE,
                    'datos_solicitante' => $datosCliente,
                    'solicitado_at' => now(),
                ],
            );

            return [
                'success' => true,
                'estado' => 'PENDIENTE',
                'message' => 'Solicitud registrada correctamente. Nuestro equipo validará los datos fiscales y emitirá su comprobante a la brevedad.',
                'solicitud_id' => $docFiscal->getKey(),
            ];
        } catch (ValidationException $e) {
            return [
                'success' => false,
                'message' => collect($e->validator->errors()->all())->first() ?? 'Datos fiscales inválidos.',
                'errors' => $e->validator->errors()->toArray(),
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Emite un DTE directamente hacia la API Fiscal / Ministerio de Hacienda.
     */
    public function emitirDteDirecto(Pedido $pedido, TipoDocumento $tipoDocumento, array $datosCliente): array
    {
        $config = $pedido->establecimiento?->configuracionFiscal
            ?? ConfiguracionFiscal::query()->where('establecimiento_id', $pedido->establecimiento_id)->first();

        $clave = 'portal-' . $pedido->getKey() . '-' . $tipoDocumento->value;
        $total = $pedido->pago?->monto_recibido !== null
            ? bcsub((string) $pedido->pago->monto_recibido, (string) ($pedido->pago->cambio_devuelto ?? 0), 2)
            : '0.00';

        $items = [];
        foreach ($pedido->detalles as $detalle) {
            if ($detalle->estado_linea?->value === 'CANCELADA') {
                continue;
            }
            $items[] = [
                'nombre' => $detalle->producto?->nombre ?? $detalle->combo?->nombre ?? 'Consumo en Restaurante',
                'cantidad' => (float) ($detalle->cantidad ?? 1),
                'precio_unitario' => (float) ($detalle->precio_unitario ?? 0),
            ];
        }

        if (empty($items)) {
            $items[] = [
                'nombre' => 'Consumo de Alimentos y Bebidas',
                'cantidad' => 1,
                'precio_unitario' => (float) $total,
            ];
        }

        $payload = [
            'establecimiento' => (string) ($config?->codigo_establecimiento ?: 'S001'),
            'puntoVenta' => (string) ($config?->codigo_punto_venta ?: 'P001'),
            'tipoDte' => $tipoDocumento->value === 'CCF' || $tipoDocumento->value === '03' ? '03' : '01',
            'cliente' => array_filter([
                'nombre' => $datosCliente['nombre'] ?? 'Consumidor Final',
                'nit' => ! empty($datosCliente['nit']) ? $datosCliente['nit'] : (! empty($datosCliente['dui']) ? $datosCliente['dui'] : null),
                'nrc' => ! empty($datosCliente['nrc']) ? $datosCliente['nrc'] : null,
                'email' => ! empty($datosCliente['email']) ? $datosCliente['email'] : null,
                'telefono' => ! empty($datosCliente['telefono']) ? $datosCliente['telefono'] : null,
                'direccion' => ! empty($datosCliente['direccion']) ? $datosCliente['direccion'] : null,
                'giro' => ! empty($datosCliente['giro']) ? $datosCliente['giro'] : null,
                'departamento' => ! empty($datosCliente['departamento']) ? $datosCliente['departamento'] : null,
                'municipio' => ! empty($datosCliente['municipio']) ? $datosCliente['municipio'] : null,
            ], fn ($v) => $v !== null),
            'items' => $items,
            'clave_reintento' => $clave,
            'referencia' => $pedido->numero_seguimiento,
            'fecha_emision' => now()->toIso8601String(),
            'monto_total' => $total,
            'tipo_documento' => $tipoDocumento->value,
            'metodo_pago' => $pedido->pago?->metodo_pago?->value ?? 'EFECTIVO',
            'receptor' => $datosCliente,
        ];

        try {
            $resultado = [];
            if ($config && $config->fiscal_habilitada) {
                $resultado = $this->fiscalGateway->enviarVenta($config, $payload);
            } elseif (app()->environment(['local', 'testing']) && config('fiscal.mock.enabled')) {
                // El mock solo es válido fuera de producción y debe habilitarse
                // explícitamente en la configuración del entorno.
                $resultado = [
                    'codigoGeneracion' => (string) Str::uuid(),
                    'selloRecepcion' => 'MOCK-SELLO-' . strtoupper(Str::random(24)),
                    'numeroControl' => 'DTE-' . ($tipoDocumento->value === 'CCF' || $tipoDocumento->value === '03' ? '03' : '01') . '-M001P001-000000000000001',
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'La configuración fiscal de la sucursal no está habilitada.',
                ];
            }

            $codigoGeneracion = $resultado['codigoGeneracion'] ?? $resultado['codigo_generacion'] ?? null;
            $selloRecepcion = $resultado['selloRecepcion'] ?? $resultado['sello_recepcion'] ?? null;
            $numeroControl = $resultado['numeroControl'] ?? $resultado['numero_control'] ?? null;

            $docFiscal = DocumentoFiscal::updateOrCreate(
                [
                    'pedido_id' => $pedido->getKey(),
                    'tipo_documento' => $tipoDocumento,
                ],
                [
                    'codigo_generacion' => $codigoGeneracion,
                    'sello_recepcion' => $selloRecepcion,
                    'numero_control' => $numeroControl,
                    'estado' => EstadoDocumentoFiscal::EMITIDO,
                    'datos_solicitante' => $datosCliente,
                    'solicitado_at' => now(),
                ],
            );

            return [
                'success' => true,
                'estado' => 'EMITIDO',
                'message' => 'Factura electrónica emitida exitosamente.',
                'dte' => [
                    'id' => $docFiscal->getKey(),
                    'codigo_generacion' => $codigoGeneracion,
                    'sello_recepcion' => $selloRecepcion,
                    'numero_control' => $numeroControl,
                ],
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'Error al emitir DTE: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Genera manualmente un DTE a partir de una solicitud pendiente.
     */
    public function generarDteSolicitud(int $documentoFiscalId): array
    {
        /** @var DocumentoFiscal $docFiscal */
        $docFiscal = DocumentoFiscal::query()
            ->with(['pedido.detalles.producto', 'pedido.detalles.combo', 'pedido.pago', 'pedido.establecimiento.configuracionFiscal'])
            ->findOrFail($documentoFiscalId);

        if ($docFiscal->estado === EstadoDocumentoFiscal::EMITIDO) {
            return [
                'success' => true,
                'estado' => 'EMITIDO',
                'message' => 'El documento ya se encuentra emitido.',
                'dte' => [
                    'codigo_generacion' => $docFiscal->codigo_generacion,
                    'sello_recepcion' => $docFiscal->sello_recepcion,
                    'numero_control' => $docFiscal->numero_control,
                ],
            ];
        }

        $pedido = $docFiscal->pedido;
        $datosCliente = $docFiscal->datos_solicitante ?? [];

        return $this->emitirDteDirecto($pedido, $docFiscal->tipo_documento, $datosCliente);
    }

    private function resolvePortalEstablishmentId(?int $establishmentId): int
    {
        if ($establishmentId !== null) {
            if (! $this->establishmentContext->canAccess($establishmentId)) {
                throw new \Illuminate\Auth\Access\AuthorizationException('No tienes acceso a esta sucursal.');
            }

            return $establishmentId;
        }

        $activeId = $this->establishmentContext->idOrNull();
        if ($activeId !== null) {
            return $activeId;
        }

        $accessible = $this->establishmentContext->accessible();
        if ($accessible->count() === 1) {
            return (int) $accessible->first()->getKey();
        }

        throw ValidationException::withMessages([
            'establecimiento_id' => 'Selecciona la sucursal para consultar o guardar el modo fiscal.',
        ]);
    }

    /**
     * Rechaza una solicitud de documento fiscal.
     */
    public function rechazarSolicitud(int $documentoFiscalId, string $motivo = 'Datos fiscales incorrectos'): DocumentoFiscal
    {
        /** @var DocumentoFiscal $docFiscal */
        $docFiscal = DocumentoFiscal::query()->findOrFail($documentoFiscalId);

        $datos = $docFiscal->datos_solicitante ?? [];
        $datos['motivo_rechazo'] = $motivo;
        $datos['rechazado_at'] = now()->toIso8601String();

        $docFiscal->update([
            'estado' => EstadoDocumentoFiscal::RECHAZADO,
            'datos_solicitante' => $datos,
        ]);

        return $docFiscal;
    }
}
