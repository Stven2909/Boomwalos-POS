<?php

namespace Tests\Feature\Gaveta;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\TipoConexionImpresora;
use App\Enums\TipoImpresora;
use App\Filament\Pages\Pos\ServiceSelection;
use App\Jobs\RegistradoraAbrirJob;
use App\Models\Establecimiento;
use App\Models\EventoAuditoria;
use App\Models\Impresora;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\ConfiguracionService;
use App\Services\Gaveta\RegistradoraSincronizacionService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PosGavetaButtonTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Establecimiento $establishment;

    private int $establishmentId;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(RolesPermissionsSeeder::class);

        $this->cashier = User::factory()->create([
            'usuario' => '32',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Pupusería Botón',
            'direccion' => 'Dirección de prueba',
        ]);
        $this->establishmentId = $this->establishment->getKey();

        $this->cashier->establecimientos()->attach($this->establishmentId);
        app(EstablishmentContextInterface::class)->set($this->establishmentId);

        SesionCaja::create([
            'establecimiento_id' => $this->establishmentId,
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => '50.00',
            'fecha_apertura' => now(),
        ]);
    }

    private function setModo(string $modo): void
    {
        app(ConfiguracionService::class)->set(RegistradoraSincronizacionService::CONFIG_MODO, $modo);
    }

    private function impresoraTicket(): Impresora
    {
        return Impresora::create([
            'nombre' => 'Registradora Ticket',
            'tipo' => TipoImpresora::TICKET,
            'conexion' => TipoConexionImpresora::PDF,
            'establecimiento_id' => $this->establishmentId,
            'activa' => true,
        ]);
    }

    public function test_boton_manual_despacha_pulso_en_modo_auto_y_audita(): void
    {
        $impresora = $this->impresoraTicket();
        $this->setModo(RegistradoraSincronizacionService::MODO_AUTO);
        $this->actingAs($this->cashier);

        Livewire::test(ServiceSelection::class)->call('abrirGaveta');

        Queue::assertPushed(
            RegistradoraAbrirJob::class,
            fn (RegistradoraAbrirJob $job) => $job->impresoraId === $impresora->getKey(),
        );
        $this->assertDatabaseHas('evento_auditorias', [
            'tipo_evento' => 'gaveta_abierta',
            'usuario_id' => $this->cashier->getKey(),
        ]);
    }

    public function test_boton_manual_en_modo_manual_avisa_y_no_despacha_pulso(): void
    {
        $this->setModo(RegistradoraSincronizacionService::MODO_MANUAL);
        $this->actingAs($this->cashier);

        Livewire::test(ServiceSelection::class)->call('abrirGaveta');

        Queue::assertNotPushed(RegistradoraAbrirJob::class);
        $this->assertSame(
            1,
            EventoAuditoria::query()->where('tipo_evento', 'gaveta_abierta')->count(),
        );
    }

    public function test_boton_manual_audita_en_la_sesion_activa(): void
    {
        $this->setModo(RegistradoraSincronizacionService::MODO_MANUAL);
        $this->actingAs($this->cashier);
        $sesion = SesionCaja::query()->whereNull('fecha_cierre')->latest('id')->firstOrFail();

        Livewire::test(ServiceSelection::class)->call('abrirGaveta');

        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_tipo' => SesionCaja::class,
            'entidad_id' => $sesion->getKey(),
            'tipo_evento' => 'gaveta_abierta',
        ]);
    }
}
