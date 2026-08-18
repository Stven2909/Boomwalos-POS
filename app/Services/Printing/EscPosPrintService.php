<?php

namespace App\Services\Printing;

use App\Enums\EstadoImpresion;
use App\Models\TrabajoImpresion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\Printer;

class EscPosPrintService
{
    public function __construct(
        private readonly PrinterConnectorFactory $connectorFactory,
    ) {}

    public function print(int $trabajoId): void
    {
        $job = $this->claim($trabajoId);

        if (! $job) {
            return;
        }

        try {
            $printer = new Printer($this->connectorFactory->create($job->impresora));
            $this->renderContent($printer, $job->contenido);
            $printer->cut();
            $printer->close();

            $job->update([
                'estado' => EstadoImpresion::IMPRESO,
                'impreso_at' => now(),
                'ultimo_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error("Error de impresión (trabajo #{$job->getKey()}): {$e->getMessage()}");

            $job->update([
                'estado' => EstadoImpresion::ERROR,
                'ultimo_error' => mb_substr($e->getMessage(), 0, 500),
            ]);
        }
    }

    private function claim(int $trabajoId): ?TrabajoImpresion
    {
        return DB::transaction(function () use ($trabajoId): ?TrabajoImpresion {
            $updated = TrabajoImpresion::where('id', $trabajoId)
                ->where('estado', EstadoImpresion::PENDIENTE->value)
                ->update([
                    'estado' => EstadoImpresion::PROCESANDO->value,
                    'intentos' => DB::raw('intentos + 1'),
                ]);

            if ($updated === 0) {
                return null;
            }

            return TrabajoImpresion::findOrFail($trabajoId);
        });
    }

    private function renderContent(Printer $printer, string $contenido): void
    {
        $lineas = explode("\n", $contenido);
        $totalLineas = count($lineas);

        foreach ($lineas as $index => $linea) {
            $linea = rtrim($linea);

            if ($linea === '') {
                $printer->feed();
                continue;
            }

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setEmphasis(false);
            $printer->selectPrintMode(Printer::MODE_FONT_A);

            if ($this->isHeaderLine($index, $totalLineas, $linea)) {
                $printer->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text($linea . "\n");
                $printer->selectPrintMode(Printer::MODE_FONT_A);
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                continue;
            }

            if ($this->isSeparatorLine($linea)) {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text($linea . "\n");
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                continue;
            }

            if ($this->isBoldLine($linea)) {
                $printer->setEmphasis(true);
                $printer->text($linea . "\n");
                $printer->setEmphasis(false);
                continue;
            }

            if ($this->isCenteredLine($linea)) {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text($linea . "\n");
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                continue;
            }

            $printer->text($linea . "\n");
        }
    }

    private function isHeaderLine(int $index, int $total, string $linea): bool
    {
        if ($index > 2) {
            return false;
        }

        return mb_strtoupper($linea) === $linea && mb_strlen($linea) > 3 && ! str_contains($linea, '---');
    }

    private function isSeparatorLine(string $linea): bool
    {
        return preg_match('/^[\-=_]{3,}$/', trim($linea)) === 1;
    }

    private function isBoldLine(string $linea): bool
    {
        $upper = mb_strtoupper($linea);

        return str_starts_with($upper, 'TOTAL')
            || str_starts_with($upper, 'PAGO')
            || str_starts_with($upper, 'RECIBIDO')
            || str_starts_with($upper, 'CAMBIO')
            || str_starts_with($upper, 'PEDIDO:')
            || str_starts_with($upper, 'COMANDA')
            || str_starts_with($upper, 'TICKET')
            || str_starts_with($upper, 'TANDA')
            || str_contains($upper, 'ATENDIDO POR');
    }

    private function isCenteredLine(string $linea): bool
    {
        $upper = mb_strtoupper($linea);

        return str_contains($upper, 'MESA ')
            || $upper === 'PARA LLEVAR · MOSTRADOR'
            || $upper === 'PARA LLEVAR'
            || str_starts_with($upper, 'FECHA');
    }
}
