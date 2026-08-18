<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if ($this->driver() === 'mysql') {
            DB::statement("ALTER TABLE trabajo_impresion MODIFY COLUMN estado ENUM('PENDIENTE','PROCESANDO','IMPRESO','ERROR') NOT NULL DEFAULT 'PENDIENTE'");
        } else {
            Schema::table('trabajo_impresion', function (Blueprint $table): void {
                $table->string('estado')->default('PENDIENTE')->change();
            });
        }

        Schema::table('trabajo_impresion', function (Blueprint $table): void {
            $table->unsignedTinyInteger('intentos')->default(0)->after('estado');
            $table->text('ultimo_error')->nullable()->after('intentos');
            $table->timestamp('impreso_at')->nullable()->after('ultimo_error');
        });
    }

    public function down(): void
    {
        Schema::table('trabajo_impresion', function (Blueprint $table): void {
            $table->dropColumn(['intentos', 'ultimo_error', 'impreso_at']);
        });

        if ($this->driver() === 'mysql') {
            DB::statement("ALTER TABLE trabajo_impresion MODIFY COLUMN estado ENUM('PENDIENTE','IMPRESO','ERROR') NOT NULL DEFAULT 'PENDIENTE'");
        }
    }

    private function driver(): string
    {
        return DB::connection()->getDriverName();
    }
};
