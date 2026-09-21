<?php

namespace App\Services\Portal;

use App\Models\Pedido;
use Illuminate\Validation\ValidationException;

class PortalFiscalValidator
{
    /**
     * Días máximos permitidos para emitir DTE desde el portal público de clientes.
     */
    public const int DIAS_MAXIMOS_EMISION = 7;

    /**
     * Valida el plazo legal y período tributario de la orden (Art. 114 y 119 C.T. / Cierre F-07).
     *
     * @throws ValidationException
     */
    public function validarPlazoFiscal(Pedido $pedido): void
    {
        $fechaOrden = $pedido->created_at ?? now();

        // 1. Validar ventana de días calendario (Máximo 7 días)
        if ($fechaOrden->diffInDays(now()) > self::DIAS_MAXIMOS_EMISION) {
            throw ValidationException::withMessages([
                'trackingPOS' => 'El plazo legal para solicitar su comprobante desde el portal ha expirado (máximo ' . self::DIAS_MAXIMOS_EMISION . ' días calendario desde el consumo). Por favor contacte a administración.',
            ]);
        }

        // 2. Validar que la orden pertenezca al período tributario mensual vigente
        if ($fechaOrden->format('Y-m') !== now()->format('Y-m')) {
            throw ValidationException::withMessages([
                'trackingPOS' => 'No es posible emitir comprobantes de períodos mensuales fiscales anteriores debido al cierre de la declaración de IVA (F-07) ante el Ministerio de Hacienda.',
            ]);
        }
    }

    /**
     * Valida las exigencias legales del Art. 119 del Código Tributario para Factura de Consumidor Final (DTE-01).
     * En ventas iguales o superiores a $200.00 USD, es obligatorio identificar al adquirente con DUI o NIT y Nombre Completo.
     *
     * @throws ValidationException
     */
    public function validarArticulo119(float $total, array $datosCliente): void
    {
        if ($total < 200.00) {
            return;
        }

        $nombre = trim((string) ($datosCliente['nombre'] ?? ''));
        $documento = trim((string) ($datosCliente['nit'] ?? $datosCliente['dui'] ?? ''));
        $docClean = preg_replace('/\D/', '', $documento);

        // Validar nombre verídico (no "Consumidor Final" ni nombres vacíos o de una sola letra)
        $nombresProhibidos = ['consumidor final', 'cliente', 'anonimo', 'anónimo', 'n/a', 'test', 'prueba'];
        if ($nombre === '' || in_array(mb_strtolower($nombre), $nombresProhibidos, true) || count(explode(' ', $nombre)) < 2) {
            throw ValidationException::withMessages([
                'nombre' => 'Por disposición legal (Art. 119 del Código Tributario de El Salvador), las compras iguales o superiores a $200.00 USD requieren el nombre completo y real del cliente.',
            ]);
        }

        // Validar documento de identificación (DUI de 9 dígitos o NIT de 14/9 dígitos)
        $len = strlen($docClean);
        if ($len !== 9 && $len !== 14) {
            throw ValidationException::withMessages([
                'nit' => 'Por disposición legal (Art. 119 del Código Tributario), las compras iguales o superiores a $200.00 USD requieren registrar un DUI (9 dígitos) o NIT (14 dígitos) válido.',
            ]);
        }
    }

    /**
     * Valida los requisitos de fondo y forma del Art. 114 del Código Tributario para Comprobante de Crédito Fiscal (DTE-03).
     *
     * @throws ValidationException
     */
    public function validarCreditoFiscalArt114(array $datosCliente): void
    {
        $errores = [];

        // 1. Razón Social / Nombre
        $nombre = trim((string) ($datosCliente['nombre'] ?? ''));
        if (mb_strlen($nombre) < 3) {
            $errores['nombre'] = 'El nombre o razón social registrada en el IVA es obligatorio para Crédito Fiscal (Art. 114 C.T.).';
        }

        // 2. NRC (Número de Registro de Contribuyente)
        $nrcClean = preg_replace('/\D/', '', (string) ($datosCliente['nrc'] ?? ''));
        if (strlen($nrcClean) < 2 || strlen($nrcClean) > 8) {
            $errores['nrc'] = 'El NRC debe contener entre 2 y 8 dígitos numéricos válidos.';
        }

        // 3. NIT del contribuyente
        $nitClean = preg_replace('/\D/', '', (string) ($datosCliente['nit'] ?? ''));
        if (strlen($nitClean) !== 9 && strlen($nitClean) !== 14) {
            $errores['nit'] = 'El NIT debe constar de 14 dígitos (o 9 dígitos si es DUI homologado).';
        }

        // 4. Giro o Actividad Económica
        $giro = trim((string) ($datosCliente['giro'] ?? ''));
        if (mb_strlen($giro) < 3) {
            $errores['giro'] = 'El giro o actividad económica registrada es obligatorio para Crédito Fiscal (Art. 114 C.T.).';
        }

        // 5. Dirección Completa
        $direccion = trim((string) ($datosCliente['direccion'] ?? ''));
        if (mb_strlen($direccion) < 5) {
            $errores['direccion'] = 'La dirección del domicilio fiscal es obligatoria para Crédito Fiscal (Art. 114 C.T.).';
        }

        // 6. Departamento (CAT-012)
        $dep = trim((string) ($datosCliente['departamento'] ?? ''));
        if (! preg_match('/^\d{2}$/', $dep) || (int) $dep < 1 || (int) $dep > 14) {
            $errores['departamento'] = 'Debe seleccionar un departamento válido conforme al catálogo CAT-012 del Ministerio de Hacienda (01 a 14).';
        }

        // 7. Municipio (CAT-013)
        $mun = trim((string) ($datosCliente['municipio'] ?? ''));
        if (! preg_match('/^\d{2}$/', $mun)) {
            $errores['municipio'] = 'Debe seleccionar un municipio válido conforme al catálogo CAT-013 del Ministerio de Hacienda.';
        }

        if (! empty($errores)) {
            throw ValidationException::withMessages($errores);
        }
    }

    /**
     * Sanitiza un arreglo de datos de cliente eliminando etiquetas HTML y caracteres de control (Anti-XSS).
     */
    public function sanitizarDatos(array $datos): array
    {
        $sanitizados = [];
        foreach ($datos as $clave => $valor) {
            if (is_string($valor)) {
                // Eliminar bloques de script y style con su contenido interno
                $limpio = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $valor);
                $limpio = preg_replace('#<style(.*?)>(.*?)</style>#is', '', (string) $limpio);
                // Eliminar tags HTML restantes y caracteres de control no imprimibles
                $limpio = strip_tags(trim((string) $limpio));
                $limpio = preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $limpio);
                $sanitizados[$clave] = $limpio;
            } elseif (is_array($valor)) {
                $sanitizados[$clave] = $this->sanitizarDatos($valor);
            } else {
                $sanitizados[$clave] = $valor;
            }
        }

        return $sanitizados;
    }
}
