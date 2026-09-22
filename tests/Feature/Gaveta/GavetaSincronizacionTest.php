<?php

namespace Tests\Feature\Gaveta;

use App\Contracts\EstablishmentContextInterface;
use App\Enums\TipoConexionImpresora;
use App\Enums\TipoImpresora;
use App\Filament\Pages\Cash\CloseSession;
use App\Filament\Pages\Cash\OpenSession;
use App\Jobs\RegistradoraAbrirJob;
use App\Jobs\RegistradoraCierreJob;
use App\Models\Establecimiento;
use App\Models\EventoAuditoria;
use App\Models\Impresora;
use App\Models\SesionCaja;
use App\Models\User;
use App\Services\CierreCajaService;
use App\Services\ConfiguracionService;
use App\Services\Gaveta\RegistradoraSincronizacionService;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class GavetaSincronizacionTest extends TestCase
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
            'usuario' => '31',
            'password' => '1234',
        ]);
        $this->cashier->assignRole('cajero');

        $this->establishment = Establecimiento::create([
            'nombre' => 'Pupusería Gaveta',
            'direccion' => 'Dirección de prueba',
        ]);
        $this->establishmentId = $this->establishment->getKey();

        $this->cashier->establecimientos()->attach($this->establishmentId);
        app(EstablishmentContextInterface::class)->set($this->establishmentId);
    }

    private function setModo(string $modo): void
    {
        app(ConfiguracionService::class)->set(RegistradoraSincronizacionService::CONFIG_MODO, $modo);
        app(ConfiguracionService::class)->set(RegistradoraSincronizacionService::CONFIG_EXIGIR, true);
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

    private function openSession(): SesionCaja
    {
        return SesionCaja::create([
            'establecimiento_id' => $this->establishmentId,
            'usuario_apertura_id' => $this->cashier->getKey(),
            'monto_inicial' => '50.00',
            'fecha_apertura' => now(),
        ]);
    }

    private function eventos(string $tipo): int
    {
        return EventoAuditoria::query()->where('tipo_evento', $tipo)->count();
    }

    public function test_abrir_turno_en_modo_manual_muestra_checkpoint_y_no_despacha_pulso(): void
    {
        $this->setModo(RegistradoraSincronizacionService::MODO_MANUAL);
        $this->actingAs($this->cashier);

        $livewire = Livewire::test(OpenSession::class);
        $livewire->set('montoInicial', '50.00')
            ->call('openSession')
            ->assertSet('checkpointVisible', true);

        Queue::assertNotPushed(RegistradoraAbrirJob::class);
        $this->assertDatabaseCount('sesion_cajas', 1);
        $this->assertSame(0, $this->eventos('gaveta_abierta'));

        $sesion = SesionCaja::first();
        $livewire->call('confirmarGavetaAbierta');

        $this->assertSame(1, $this->eventos('gaveta_abierta'));
        $this->assertDatabaseHas('evento_auditorias', [
            'entidad_tipo' => SesionCaja::class,
            'entidad_id' => $sesion->getKey(),
            'tipo_evento' => 'gaveta_abierta',
        ]);
    }

    public function test_abrir_turno_en_modo_auto_despacha_pulso_y_no_muestra_checkpoint(): void
    {
        $impresora = $this->impresoraTicket();
        $this->setModo(RegistradoraSincronizacionService::MODO_AUTO);
        $this->actingAs($this->cashier);

        Livewire::test(OpenSession::class)
            ->set('montoInicial', '50.00')
            ->call('openSession');

        Queue::assertPushed(
            RegistradoraAbrirJob::class,
            fn (RegistradoraAbrirJob $job) => $job->impresoraId === $impresora->getKey(),
        );
        $this->assertSame(1, $this->eventos('gaveta_abierta'));
    }

    public function test_abrir_turno_en_modo_auto_sin_impresora_cae_a_checkpoint_manual(): void
    {
        $this->setModo(RegistradoraSincronizacionService::MODO_AUTO);
        $this->actingAs($this->cashier);

        Livewire::test(OpenSession::class)
            ->set('montoInicial', '50.00')
            ->call('openSession')
            ->assertSet('checkpointVisible', true);

        Queue::assertNotPushed(RegistradoraAbrirJob::class);
    }

    public function test_cerrar_turno_en_modo_auto_encola_corte_y_pulso_y_audita(): void
    {
        $impresora = $this->impresoraTicket();
        $this->setModo(RegistradoraSincronizacionService::MODO_AUTO);

        $sesion = $this->openSession();
        $cerrada = app(CierreCajaService::class)->cerrar($sesion, '50.00', $this->cashier);

        $this->assertNotNull($cerrada->fecha_cierre);
        Queue::assertPushed(
            RegistradoraCierreJob::class,
            fn (RegistradoraCierreJob $job) => $job->sesionCajaId === $sesion->getKey()
                && $job->impresoraId === $impresora->getKey(),
        );
        $this->assertSame(1, $this->eventos('gaveta_cerrada'));
    }

    public function test_cerrar_turno_auto_sin_impresora_no_rompe_el_cierre(): void
    {
        $this->setModo(RegistradoraSincronizacionService::MODO_AUTO);

        $sesion = $this->openSession();
        $cerrada = app(CierreCajaService::class)->cerrar($sesion, '50.00', $this->cashier);

        $this->assertNotNull($cerrada->fecha_cierre);
        Queue::assertNotPushed(RegistradoraCierreJob::class);
        $this->assertSame(1, $this->eventos('gaveta_cerrada'));
    }

    public function test_close_session_exige_confirmar_gaveta_en_modo_manual(): void
    {
        $this->setModo(RegistradoraSincronizacionService::MODO_MANUAL);
        $this->actingAs($this->cashier);
        $sesion = $this->openSession();

        Livewire::test(CloseSession::class)
            ->set('efectivoContado', '50.00')
            ->call('closeSession')
            ->assertSet('feedback', 'Confirma que cerraste la gaveta de dinero y resguardaste el efectivo antes de firmar el cierre.');

        $this->assertNull($sesion->fresh()->fecha_cierre);

        Livewire::test(CloseSession::class)
            ->set('efectivoContado', '50.00')
            ->set('gavetaCerrada', true)
            ->call('closeSession');

        $this->assertNotNull($sesion->fresh()->fecha_cierre);
    }
}
