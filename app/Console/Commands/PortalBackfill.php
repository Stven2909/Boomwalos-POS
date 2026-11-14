<?php

namespace App\Console\Commands;

use App\Application\Fiscal\FiscalApiException;
use App\Application\Fiscal\FiscalClient;
use App\Enums\EstadoComercialPedido;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\VentaFiscalPos;
use App\Services\Portal\PortalSnapshotRegistrar;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class PortalBackfill extends Command
{
    protected $signature = 'portal:backfill
        {--desde= : Fecha inicial (Y-m-d). Por defecto, los últimos días del mes de --hasta.}
        {--hasta= : Fecha final (Y-m-d). Por defecto, hoy.}
        {--limite=500 : Máximo de pedidos a procesar.}
        {--dry-run : Valida y reporta sin enviar a ApiFact.}';

    protected $description = 'Carga inicial del portal: snapshot y vínculo con DTE existentes.';

    public function handle(PortalSnapshotRegistrar $snapshots, FiscalClient $client): int
    {
        try {
            [$desde, $hasta] = $this->ventana();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $limite = max(1, min(5000, (int) $this->option('limite')));
        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Ventana %s → %s%s',
            $desde->toDateString(),
            $hasta->toDateString(),
            $dryRun ? ' (dry-run)' : '',
        ));

        $stats = [
            'enviados' => 0,
            'vinculados' => 0,
            'sin_vinculo' => 0,
            'revision' => 0,
            'omitidos' => 0,
            'errores' => 0,
        ];
        $avisos = [];

        $pedidos = Pedido::query()
            ->whereIn('estado_comercial', [
                EstadoComercialPedido::COBRADO->value,
                EstadoComercialPedido::CERRADO->value,
            ])
            ->whereBetween('created_at', [
                $desde->copy()->startOfDay(),
                $hasta->copy()->endOfDay(),
            ])
            ->whereHas('pago')
            ->with(['pago', 'establecimiento', 'detalles.producto', 'detalles.combo'])
            ->orderBy('id')
            ->limit($limite)
            ->get();

        foreach ($pedidos as $pedido) {
            /** @var Pago|null $pago */
            $pago = $pedido->pago;

            if ($pago === null) {
                $stats['omitidos']++;

                continue;
            }

            $configuracion = $snapshots->configuracionPara($pedido);
            $payload = $snapshots->payload($pedido, $pago);

            if ($configuracion === null || $payload === null) {
                $stats['omitidos']++;

                continue;
            }

            $vinculo = $this->vinculoPara($pedido);

            if ($vinculo === null) {
                $stats['sin_vinculo']++;
            } else {
                $payload['vinculo'] = $vinculo;
            }

            if ($dryRun) {
                $stats['enviados']++;

                if ($vinculo !== null) {
                    $stats['vinculados']++;
                }

                continue;
            }

            try {
                $client->enviarSnapshotPortalPedido($configuracion, $payload);
                $stats['enviados']++;

                if ($vinculo !== null) {
                    $stats['vinculados']++;
                }
            } catch (FiscalApiException $exception) {
                if ($exception->status === 409) {
                    $stats['revision']++;
                    $avisos[] = $payload['tracking'].': '.$this->codigoError($exception);
                } else {
                    $stats['errores']++;
                    $avisos[] = $payload['tracking'].': HTTP '.$exception->status;
                }

                Log::warning('Falló la carga inicial del portal.', [
                    'tracking' => $payload['tracking'],
                    'status' => $exception->status,
                    'error' => $exception->getMessage(),
                ]);
            } catch (Throwable $exception) {
                $stats['errores']++;
                $avisos[] = $payload['tracking'].': '.$exception->getMessage();

                Log::warning('Falló la carga inicial del portal.', [
                    'tracking' => $payload['tracking'],
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ['Enviados', $stats['enviados']],
                ['Con vínculo DTE', $stats['vinculados']],
                ['Sin DTE (solo snapshot)', $stats['sin_vinculo']],
                ['En revisión (409)', $stats['revision']],
                ['Omitidos', $stats['omitidos']],
                ['Errores', $stats['errores']],
            ],
        );

        foreach (array_slice($avisos, 0, 25) as $aviso) {
            $this->warn($aviso);
        }

        if (count($avisos) > 25) {
            $this->warn('... y '.(count($avisos) - 25).' avisos más.');
        }

        return self::SUCCESS;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function ventana(): array
    {
        $hasta = $this->fecha($this->option('hasta')) ?? now()->startOfDay();
        $hasta = $hasta->copy()->startOfDay();

        $dias = max(1, (int) config('portal.backfill_dias', 7));
        $desde = $this->fecha($this->option('desde'))
            ?? $hasta->copy()->subDays($dias - 1);

        $corte = $this->fecha(config('portal.corte'));
        $inicioMes = $hasta->copy()->startOfMonth();

        if ($corte !== null && $corte->gt($desde)) {
            $desde = $corte->copy();
        }

        if ($desde->lt($inicioMes)) {
            $desde = $inicioMes->copy();
        }

        if ($desde->gt($hasta)) {
            throw new InvalidArgumentException('La fecha inicial no puede ser posterior a la final.');
        }

        if ($desde->diffInDays($hasta) > $dias - 1) {
            $desde = $hasta->copy()->subDays($dias - 1);

            if ($corte !== null && $corte->gt($desde)) {
                $desde = $corte->copy();
            }

            if ($desde->lt($inicioMes)) {
                $desde = $inicioMes->copy();
            }
        }

        return [$desde, $hasta];
    }

    private function fecha(mixed $valor): ?Carbon
    {
        $raw = trim((string) $valor);

        if ($raw === '') {
            return null;
        }

        try {
            $fecha = Carbon::createFromFormat('!Y-m-d', $raw);
        } catch (Throwable) {
            throw new InvalidArgumentException("Fecha inválida: {$raw}. Usa el formato YYYY-MM-DD.");
        }

        if ($fecha === false || $fecha->format('Y-m-d') !== $raw) {
            throw new InvalidArgumentException("Fecha inválida: {$raw}. Usa el formato YYYY-MM-DD.");
        }

        return $fecha->startOfDay();
    }

    /** Identificadores exactos del DTE existente, si los hay. */
    private function vinculoPara(Pedido $pedido): ?array
    {
        $venta = VentaFiscalPos::query()
            ->where('pedido_id', $pedido->getKey())
            ->orderByDesc('id')
            ->first();

        if ($venta === null) {
            return null;
        }

        $codigo = trim((string) $venta->codigo_generacion);
        $control = trim((string) $venta->numero_control);
        $sello = trim((string) $venta->sello_recepcion);

        if ($codigo === '' && ($control === '' || $sello === '')) {
            return null;
        }

        $vinculo = [];

        if ($codigo !== '') {
            $vinculo['codigo_generacion'] = $codigo;
        }

        if ($control !== '' && $sello !== '') {
            $vinculo['numero_control'] = $control;
            $vinculo['sello_recepcion'] = $sello;
        }

        return $vinculo;
    }

    private function codigoError(FiscalApiException $exception): string
    {
        $body = json_decode($exception->getMessage(), true);

        if (is_array($body)) {
            return (string) ($body['error'] ?? $body['codigo'] ?? $body['message'] ?? 'ERROR');
        }

        $mensaje = trim($exception->getMessage());

        return $mensaje !== '' ? mb_substr($mensaje, 0, 120) : 'ERROR';
    }
}
