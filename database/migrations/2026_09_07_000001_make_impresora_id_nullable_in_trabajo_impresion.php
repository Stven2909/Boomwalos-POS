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
            DB::statement('ALTER TABLE trabajo_impresion MODIFY COLUMN impresora_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('trabajo_impresion', function (Blueprint $table): void {
                $table->foreignId('impresora_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if ($this->driver() === 'mysql') {
            DB::statement('ALTER TABLE trabajo_impresion MODIFY COLUMN impresora_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('trabajo_impresion', function (Blueprint $table): void {
                $table->foreignId('impresora_id')->change();
            });
        }
    }

    private function driver(): string
    {
        return DB::connection()->getDriverName();
    }
};