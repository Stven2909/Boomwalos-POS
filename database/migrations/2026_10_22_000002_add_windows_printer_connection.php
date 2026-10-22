<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impresoras', function (Blueprint $table): void {
            $table->string('ruta_windows', 255)->nullable();
        });

        $this->changeMysqlEnum(true);
    }

    public function down(): void
    {
        if (DB::table('impresoras')->where('conexion', 'WINDOWS')->exists()) {
            throw new RuntimeException('Restaura las conexiones WINDOWS antes de revertir esta migración.');
        }

        $this->changeMysqlEnum(false);

        Schema::table('impresoras', function (Blueprint $table): void {
            $table->dropColumn('ruta_windows');
        });
    }

    private function changeMysqlEnum(bool $add): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $column = DB::selectOne("SHOW COLUMNS FROM impresoras WHERE Field = 'conexion'");
        $type = $column->Type;
        $type = $add
            ? substr($type, 0, -1).",'WINDOWS')"
            : str_replace(",'WINDOWS'", '', $type);
        $default = $column->Default === null ? '' : ' DEFAULT '.DB::connection()->getPdo()->quote($column->Default);
        $nullable = $column->Null === 'YES' ? ' NULL' : ' NOT NULL';

        DB::statement('ALTER TABLE impresoras MODIFY COLUMN conexion '.$type.$nullable.$default);
    }
};
