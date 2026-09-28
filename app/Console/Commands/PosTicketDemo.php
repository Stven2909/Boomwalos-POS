<?php

namespace App\Console\Commands;

use App\Application\Printing\RenderCustomerTicket;
use App\Contracts\EstablishmentContextInterface;
use App\Enums\EstadoComercialPedido;
use App\Enums\EstadoImpresion;
use App\Enums\MetodoPago;
use App\Enums\TipoImpresora;
use App\Enums\TipoPedido;
use App\Enums\TipoTrabajoImpresion;
use App\Models\Combo;
use App\Models\Establecimiento;
use App\Models\Impresora;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\SesionCaja;
use App\Models\TrabajoImpresion;
use App\Models\User;
use App\Services\PedidoService;
use App\Services\Printing\EscPosPrintService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PosTicketDemo extends Command
{
    protected $signature = 'pos:ticket-demo
        {--pedido= : ID de un pedido existente para reimprimir su ticket de cliente}
        {--establecimiento= : ID de la sucursal (se usa el contexto o la única sucursal si se omite)}
        {--solo-texto : Muestra el contenido del ticket sin encolar ni imprimir}';

    protected $description = 'Simula un ticket de cliente real (productos, masas, precios, total, cambio) y lo imprime como PDF 80mm.';

    public function __construct(
        private readonly PedidoService $pedidoService,
        private readonly RenderCustomerTicket $ticketRenderer,
        private readonly EscPosPrintService $printed,
    ) {
        parent::__construct();
    }

    public function handle(EstablishmentContextInterface $context): int
    {
        try {
            $establishmentId = $this->resolveEstablishmentId($context);
            $context->set($establishmentId);

            $actor = $this->resolveActor();

            if ($this->option('pedido')) {
                $pedido = Pedido::query()->with('pago')->findOrFail((int) $this->option('pedido'));
                $pago = $pedido->pago ?? $this->createPago($pedido, $context);
            } else {
                $pedido = $this->buildDemoOrder($context, $actor);
                $pago = $this->createPago($pedido, $context);
            }

            $pedido->loadMissing('detalles.producto', 'detalles.combo', 'detalles.detallePedidoNotas.notaCocina');
            $contenido = $this->ticketRenderer->render($pedido, $pago, $actor);

            if ($this->option('solo-texto')) {
                $this->line($contenido);

                return self::SUCCESS;
            }

            $job = $this->queueTicket($pedido, $contenido, (bool) $this->option('pedido'));
            $this->printed->print($job->getKey());

            $job->refresh();

            if ($job->estado === EstadoImpresion::ERROR) {
                throw new \RuntimeException('El ticket no pudo imprimirse. Revisa la impresora de ticket configurada en el Monitor de Impresión.');
            }

            $this->summary($pedido, $pago, $job);

            return self::SUCCESS;
        } catch (Throwable $e) {
            Log::error("pos:ticket-demo falló: {$e->getMessage()}", ['trace' => $e->getTraceAsString()]);

            $this->error($this->friendlyMessage($e));

            return self::FAILURE;
        }
    }

    private function resolveEstablishmentId(EstablishmentContextInterface $context): int
    {
        $explicit = $this->option('establecimiento');

        if ($explicit !== null) {
            $found = Establecimiento::query()->find((int) $explicit);

            if (! $found) {
                throw new \RuntimeException("La sucursal #{$explicit} no existe. Verifica el ID con pos:establecimientos.");
            }

            return (int) $explicit;
        }

        $resolved = $context->idOrNull();

        if ($resolved !== null) {
            return $resolved;
        }

        $only = Establecimiento::query()->count() === 1 ? Establecimiento::query()->first() : null;

        if ($only) {
            return (int) $only->getKey();
        }

        throw new \RuntimeException('No se pudo determinar la sucursal. Pasa el ID con --establecimiento=<id>.');
    }

    private function resolveActor(): User
    {
        $actor = User::query()->firstWhere('usuario', 'admin')
            ?? User::query()->whereHas('roles', fn ($q) => $q->where('name', 'administrador'))->first()
            ?? User::query()->first();

        if (! $actor) {
            throw new \RuntimeException('No hay usuarios en el sistema. Crea al menos uno antes de generar el ticket.');
        }

        return $actor;
    }

    private function buildDemoOrder(EstablishmentContextInterface $context, User $actor): Pedido
    {
        $pupusa = Producto::query()->where('nombre', 'Pupusa de Queso')->first();
        $horchata = Producto::query()->where('nombre', 'Horchata')->first();
        $combo = Combo::query()->where('nombre', 'Combo #2')->first() ?? Combo::query()->first();

        if (! $pupusa || ! $horchata || ! $combo) {
            throw new \RuntimeException('El catálogo real no está cargado. Ejecuta el CatalogoRealSeeder antes de simular el ticket.');
        }

        $pedido = $this->pedidoService->startOrder(TipoPedido::PARA_LLEVAR, $actor);
        $this->pedidoService->addProduct($pedido, $pupusa, $actor, 'ARROZ');
        $this->pedidoService->addProduct($pedido, $horchata, $actor);
        $this->pedidoService->addCombo($pedido, $combo, $this->buildSelection($combo), $actor);

        $pedido->update(['estado_comercial' => EstadoComercialPedido::COBRADO]);

        return $pedido->fresh(['detalles', 'detalles.producto', 'detalles.combo']);
    }

    private function buildSelection(Combo $combo): array
    {
        $selection = [];

        foreach ($combo->opcionesCombo()->with('productos')->get()->sortBy('id') as $option) {
            $products = $option->productos;
            $slots = (int) $option->cantidad_requerida;

            if ($products->isEmpty() || $slots < 1) {
                continue;
            }

            $massProducts = $products->where('requiere_masa', true)->values();
            $nonMass = $products->where('requiere_masa', false)->values();

            if ($massProducts->isNotEmpty() && $massProducts->count() >= 2) {
                $first = $this->pick($massProducts, 'Pupusa de Queso', 0);
                $second = $this->pick($massProducts->where('id', '!=', $first->id)->values(), 'Pupusa Revuelta', 0);
                $firstQty = (int) ceil($slots / 2);

                $selection[(string) $option->id] = [
                    (string) $first->id => ['ARROZ' => $firstQty],
                    (string) $second->id => ['MAIZ' => $slots - $firstQty],
                ];
            } elseif ($massProducts->isNotEmpty()) {
                $first = $massProducts->first();
                $selection[(string) $option->id] = [(string) $first->id => ['ARROZ' => $slots]];
            } else {
                $first = $this->pick($nonMass, 'Soda', 0);
                $second = $this->pick($nonMass->where('id', '!=', $first->id)->values(), 'Kolashampagne', 0);
                $firstQty = (int) ceil($slots / 2);

                $selection[(string) $option->id] = [
                    (string) $first->id => $firstQty,
                    (string) $second->id => $slots - $firstQty,
                ];
            }
        }

        return $selection;
    }

    private function pick(Collection $products, string $preferred, int $fallbackIndex): Producto
    {
        if ($products->isEmpty()) {
            throw new \RuntimeException('El combo no tiene productos válidos para la simulación.');
        }

        return $products->first(fn (Producto $product): bool => $product->nombre === $preferred)
            ?? $products->get(min($fallbackIndex, $products->count() - 1))
            ?? $products->first();
    }

    private function createPago(Pedido $pedido, EstablishmentContextInterface $context): Pago
    {
        $sesion = SesionCaja::query()
            ->where('establecimiento_id', $context->id())
            ->whereNull('fecha_cierre')
            ->latest('id')
            ->first();

        if (! $sesion) {
            $sesion = SesionCaja::create([
                'establecimiento_id' => $context->id(),
                'usuario_apertura_id' => $pedido->usuario_id,
                'monto_inicial' => 0,
                'fecha_apertura' => now(),
            ]);
        }

        $total = round($pedido->total(), 2);
        $recibido = $total <= 20.00 ? 20.00 : (float) (ceil($total) + 1);

        return Pago::create([
            'pedido_id' => $pedido->getKey(),
            'sesion_caja_id' => $sesion->getKey(),
            'metodo_pago' => MetodoPago::EFECTIVO,
            'monto_recibido' => $recibido,
            'cambio_devuelto' => round($recibido - $total, 2),
        ]);
    }

    private function queueTicket(Pedido $pedido, string $contenido, bool $isReprint): TrabajoImpresion
    {
        $printer = Impresora::buscar(TipoImpresora::TICKET);

        if (! $printer) {
            throw new \RuntimeException('No hay impresora de ticket configurada. Crea una en Panel → Ajustes → Impresoras.');
        }

        return TrabajoImpresion::create([
            'impresora_id' => $printer->getKey(),
            'pedido_id' => $pedido->getKey(),
            'tipo_trabajo' => TipoTrabajoImpresion::TICKET,
            'estado' => EstadoImpresion::PENDIENTE,
            'contenido' => $contenido,
            'original_uid' => 'demo-'.uniqid().'-'.Str::random(6),
            'es_reimpresion' => $isReprint,
            'motivo_reimpresion' => $isReprint ? 'Ticket de muestra generado por pos:ticket-demo' : null,
        ]);
    }

    private function summary(Pedido $pedido, Pago $pago, TrabajoImpresion $job): void
    {
        $lines = [
            'Ticket generado como un pedido real:',
            '  Pedido:   '.($pedido->codigo_corto ? '#'.$pedido->codigo_corto.' ' : '').$pedido->numero_seguimiento,
            '  Total:    $'.number_format($pedido->total(), 2),
            '  Pago:     '.$pago->metodo_pago->label().' · Recibido $'.number_format((float) $pago->monto_recibido, 2).' · Cambio $'.number_format((float) $pago->cambio_devuelto, 2),
            '  Trabajo:  #'.$job->getKey().' ('.$job->estado->label().')',
            '',
            '  PDF (disco):  storage/app/public/impresiones/trabajo-'.$job->getKey().'.pdf',
            '  PDF (web):    /storage/impresiones/trabajo-'.$job->getKey().'.pdf',
            '  Monitor:      Panel → Ajustes → Monitor de Impresión',
            '  Vista admin:  /admin/impresion/trabajo/'.$job->getKey().'/pdf',
        ];

        $this->info(implode("\n", $lines));
    }

    private function friendlyMessage(Throwable $e): string
    {
        if ($e instanceof ModelNotFoundException) {
            return 'El pedido indicado no existe. Verifica el ID con --pedido=<id>.';
        }

        if ($e instanceof \RuntimeException) {
            return $e->getMessage();
        }

        return 'No se pudo generar el ticket de cliente. Revisa el registro de la aplicación para más detalles.';
    }
}
