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
            $table->foreignId('establecimiento_id')->nullable()->after('tipo')->constrained('establecimientos')->nullOnDelete();
            $table->boolean('activa')->default(true)->after('establecimiento_id');
            $table->string('ip', 45)->nullable()->after('activa');
            $table->unsignedSmallInteger('puerto')->default(9100)->after('ip');
            $table->string('dispositivo_usb')->nullable()->after('puerto');
            $table->timestamp('ultima_conexion_exitosa_at')->nullable()->after('dispositivo_usb');
        });

        if ($this->driver() === 'mysql') {
            DB::statement("ALTER TABLE impresoras ADD COLUMN conexion ENUM('RED','USB') NOT NULL DEFAULT 'RED' AFTER configuracion");
        } else {
            Schema::table('impresoras', function (Blueprint $table): void {
                $table->string('conexion')->default('RED')->after('configuracion');
            });
        }
    }

    public function down(): void
    {
        Schema::table('impresoras', function (Blueprint $table): void {
            $table->dropForeign(['establecimiento_id']);
            $table->dropColumn([
                'establecimiento_id',
                'activa',
                'ip',
                'puerto',
                'dispositivo_usb',
                'ultima_conexion_exitosa_at',
                'conexion',
            ]);
        });
    }

    private function driver(): string
    {
        return DB::connection()->getDriverName();
    }
};
