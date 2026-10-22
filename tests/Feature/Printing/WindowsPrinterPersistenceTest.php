<?php

namespace Tests\Feature\Printing;

use App\Enums\TipoConexionImpresora;
use App\Models\Impresora;
use App\Models\User;
use App\Filament\Resources\Impresoras\Pages\CreateImpresora;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Livewire\Livewire;
use Tests\TestCase;

class WindowsPrinterPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Estas pruebas requieren SQLite :memory:, nunca la base de producción.');
        }
    }

    public function test_windows_connection_and_path_round_trip(): void
    {
        $printer = Impresora::create(['nombre' => 'RECIBOS test', 'tipo' => 'TICKET', 'conexion' => 'WINDOWS', 'ruta_windows' => '\\\\CAJA-PC\\RECIBOS', 'activa' => false]);
        $this->assertSame(TipoConexionImpresora::WINDOWS, $printer->fresh()->conexion);
        $this->assertSame('\\\\CAJA-PC\\RECIBOS', $printer->fresh()->direccionConexion());
    }

    public function test_rollback_is_blocked_while_windows_connections_exist(): void
    {
        Impresora::create(['nombre' => 'RECIBOS test', 'tipo' => 'TICKET', 'conexion' => 'WINDOWS', 'ruta_windows' => '\\\\CAJA-PC\\RECIBOS']);
        $migration = require database_path('migrations/2026_10_22_000002_add_windows_printer_connection.php');
        $this->expectException(RuntimeException::class);
        $migration->down();
    }

    public function test_rollback_and_reapply_preserve_existing_connections(): void
    {
        $printer = Impresora::create(['nombre' => 'PDF test', 'tipo' => 'TICKET', 'conexion' => 'PDF']);
        $migration = require database_path('migrations/2026_10_22_000002_add_windows_printer_connection.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('impresoras', 'ruta_windows'));
        $migration->up();
        $this->assertSame(TipoConexionImpresora::PDF, $printer->fresh()->conexion);
        $this->assertNull($printer->fresh()->ruta_windows);
    }

    public function test_form_validates_and_creates_a_shared_windows_printer(): void
    {
        $this->seed(RolesPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('administrador');
        $this->actingAs($admin);

        Livewire::test(CreateImpresora::class)
            ->fillForm(['nombre' => 'RECIBOS form', 'tipo' => 'TICKET', 'conexion' => 'WINDOWS', 'ruta_windows' => '\\\\CAJA-PC\\RECIBOS\\extra', 'activa' => false])
            ->call('create')
            ->assertHasFormErrors(['ruta_windows'])
            ->fillForm(['ruta_windows' => '\\\\CAJA-PC\\RECIBOS'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('impresoras', ['nombre' => 'RECIBOS form', 'conexion' => 'WINDOWS', 'ruta_windows' => '\\\\CAJA-PC\\RECIBOS', 'activa' => false]);
    }
}
