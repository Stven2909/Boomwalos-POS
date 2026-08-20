<?php

namespace App\Services\Portal;

use App\Contracts\FiscalGatewayInterface;
use App\Enums\EstadoDocumentoFiscal;
use App\Enums\TipoDocumento;
use App\Models\Configuracion;
use App\Models\ConfiguracionFiscal;
use App\Models\DocumentoFiscal;
use App\Models\Establecimiento;
use App\Models\Pedido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class PortalFiscalService
{
    public const string MODO_MANUAL = 'MANUAL';
    public const string MODO_AUTOMATICO = 'AUTOMATICO';
    public const string MODO_HIBRIDO = 'HIBRIDO';

    public function __construct(
        private readonly FiscalGatewayInterface $fiscalGateway,
    ) {}

    /**
     * Busca una orden por su número de seguimiento o código corto.
     */
    public function buscarOrden(string $tracking): ?array
    {
        $tracking = trim($tracking);

        /** @var Pedido|null $pedido */
        $pedido = Pedido::query()
            ->with([
                'detalles.producto',
                'detalles.combo',
                'detalles.detallePedidoNotas.notaCocina',
                'pago',
                'mesa',
                'usuario',
                'establecimiento',
            ])
            ->where('numero_seguimiento', $tracking)
            ->orWhere(function (Builder $query) use ($tracking): void {
                if (is_numeric($tracking)) {
                    $query->where('codigo_corto', (int) $tracking);
                }
            })
            ->latest('id')
            ->first();

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
            foreach ($detalle->seleccion_combo ?? [] as $grupo) {
                foreach ($grupo['items'] ?? [] as $itemCombo) {
                    if (! empty($itemCombo['nombre']) && ! empty($itemCombo['cantidad'])) {
                        $opcionesCombo[] = "{$itemCombo['cantidad']}x {$itemCombo['nombre']}";
                        $descripciones[] = "{$itemCombo['cantidad']}x {$itemCombo['nombre']}";
                    }
                }
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

        return [
            'id' => $pedido->getKey(),
            'tracking_number' => $pedido->numero_seguimiento,
            'codigo_corto' => $pedido->codigo_corto,
            'fecha' => $pedido->created_at?->format('d/m/Y h:i A') ?? now()->format('d/m/Y h:i A'),
            'cliente' => $pedido->usuario?->nombre ?? 'Consumidor Final',
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

        return self::MODO_HIBRIDO;
    }

    /**
     * Guarda el modo de emisión del portal.
     */
    public function guardarModoEmision(string $modo, ?int $establecimientoId = null): string
    {
        $modo = strtoupper(trim($modo));
        if (! in_array($modo, [self::MODO_MANUAL, self::MODO_AUTOMATICO, self::MODO_HIBRIDO], true)) {
            $modo = self::MODO_HIBRIDO;
        }

        $establecimientoId ??= Establecimiento::query()->value('id') ?? 1;

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
     * Procesa la solicitud enviada por un cliente desde WebFact.
     */
    public function procesarSolicitudCliente(array $datos): array
    {
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
            return ['success' => false, 'message' => 'No se encontró la orden solicitada.'];
        }

        // Mapear tipo de documento
        $codigoDte = (string) ($datos['tipoDTE'] ?? '01');
        $tipoDocumento = match ($codigoDte) {
            '03', 'CCF' => TipoDocumento::CCF,
            default => TipoDocumento::FACTURA,
        };

        // Verificar si ya existe documento fiscal emitido
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

        $datosCliente = [
            'nombre' => trim((string) ($datos['nombre'] ?? '')),
            'nit' => trim((string) ($datos['nit'] ?? '')),
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

        $modo = $this->obtenerModoEmision($pedido->establecimiento_id);

        // Decidir si emite automáticamente o pasa a validación manual
        $debeEmitirAutomatico = ($modo === self::MODO_AUTOMATICO) || ($modo === self::MODO_HIBRIDO && $tipoDocumento === TipoDocumento::FACTURA);

        if ($debeEmitirAutomatico) {
            return $this->emitirDteDirecto($pedido, $tipoDocumento, $datosCliente);
        }

        // Modo Manual: Registrar solicitud como PENDIENTE
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
            'message' => 'Solicitud registrada correctamente. Nuestro equipo validará los datos y emitirá su comprobante.',
            'solicitud_id' => $docFiscal->getKey(),
        ];
    }

    /**
     * Emite un DTE directamente hacia la API Fiscal / Ministerio de Hacienda.
     */
    public function emitirDteDirecto(Pedido $pedido, TipoDocumento $tipoDocumento, array $datosCliente): array
    {
        $config = $pedido->establecimiento?->configuracionFiscal
            ?? ConfiguracionFiscal::query()->where('establecimiento_id', $pedido->establecimiento_id)->first();

        $clave = 'portal-' . $pedido->getKey() . '-' . time();
        $total = $pedido->pago?->monto_recibido !== null
            ? bcsub((string) $pedido->pago->monto_recibido, (string) ($pedido->pago->cambio_devuelto ?? 0), 2)
            : '0.00';

        $payload = [
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
            } else {
                // Simulación en entornos de desarrollo / mock
                $resultado = [
                    'fiscal_sale_id' => 'GEN-' . strtoupper(Str::random(8)),
                    'codigo_generacion' => (string) Str::uuid(),
                    'sello_recepcion' => 'SELLO-' . strtoupper(Str::random(24)),
                    'numero_control' => 'DTE-01-' . strtoupper(Str::random(10)),
                ];
            }

            $codigoGeneracion = $resultado['codigo_generacion'] ?? (string) Str::uuid();
            $selloRecepcion = $resultado['sello_recepcion'] ?? ('REC-' . strtoupper(Str::random(20)));
            $numeroControl = $resultado['numero_control'] ?? ('DTE-' . $tipoDocumento->value . '-' . rand(1000, 9999));

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
