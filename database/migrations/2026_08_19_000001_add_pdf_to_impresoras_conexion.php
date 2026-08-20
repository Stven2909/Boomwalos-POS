<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE impresoras MODIFY COLUMN conexion ENUM('RED','USB','PDF') NOT NULL DEFAULT 'PDF'");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE impresoras MODIFY COLUMN conexion ENUM('RED','USB') NOT NULL DEFAULT 'RED'");
        }
    }
};
