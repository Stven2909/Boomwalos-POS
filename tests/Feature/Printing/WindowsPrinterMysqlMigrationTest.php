<?php

namespace Tests\Feature\Printing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class WindowsPrinterMysqlMigrationTest extends TestCase
{
    public function test_mysql_migration_in_an_exclusively_disposable_database(): void
    {
        if (getenv('PRINTER_MYSQL_MIGRATION_TEST') !== '1') {
            $this->markTestSkipped('Requiere MySQL de pruebas y PRINTER_MYSQL_MIGRATION_TEST=1; nunca usa la base de la aplicación.');
        }

        $config = config('database.connections.mysql');
        $originalDefault = DB::getDefaultConnection();
        $name = 'codex_printer_test_'.bin2hex(random_bytes(6));
        $this->assertNotSame($config['database'], $name);
        $config['url'] = null;
        $config['database'] = null;
        config(['database.connections.printer_test_admin' => $config]);
        $admin = DB::connection('printer_test_admin');
        $created = false;

        try {
            $admin->statement('CREATE DATABASE `'.$name.'`');
            $created = true;
            $config['database'] = $name;
            config(['database.connections.printer_test' => $config]);
            DB::setDefaultConnection('printer_test');
            Schema::clearResolvedInstance('db.schema');
            DB::statement("CREATE TABLE impresoras (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, conexion ENUM('RED','USB','PDF','CUSTOM') NOT NULL DEFAULT 'USB')");
            $migration = require database_path('migrations/2026_10_22_000002_add_windows_printer_connection.php');
            $migration->up();
            $column = DB::selectOne("SHOW COLUMNS FROM impresoras WHERE Field = 'conexion'");
            $this->assertSame('USB', $column->Default);
            $this->assertStringContainsString("'CUSTOM'", $column->Type);
            DB::table('impresoras')->insert(['conexion' => 'WINDOWS', 'ruta_windows' => '\\\\CAJA-PC\\RECIBOS']);
            $this->assertSame('\\\\CAJA-PC\\RECIBOS', DB::table('impresoras')->value('ruta_windows'));
            try {
                $migration->down();
                $this->fail('El rollback debe bloquearse mientras existan conexiones WINDOWS.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('Restaura', $exception->getMessage());
            }
            DB::table('impresoras')->update(['conexion' => 'USB']);
            $migration->down();
            $column = DB::selectOne("SHOW COLUMNS FROM impresoras WHERE Field = 'conexion'");
            $this->assertSame('USB', $column->Default);
            $this->assertStringNotContainsString("'WINDOWS'", $column->Type);
            $this->assertFalse(Schema::hasColumn('impresoras', 'ruta_windows'));
        } finally {
            DB::setDefaultConnection($originalDefault);
            Schema::clearResolvedInstance('db.schema');
            DB::purge('printer_test');
            if ($created) {
                $admin->statement('DROP DATABASE `'.$name.'`');
            }
        }
    }
}
