<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajo_impresion', function (Blueprint $table) {
            $table->enum('tipo_trabajo', ['TICKET', 'COMANDA'])->default('COMANDA')->after('impresora_id');
            $table->boolean('es_reimpresion')->default(false)->after('tipo_trabajo');
            $table->foreignId('reimpresion_de_id')
                ->nullable()
                ->after('es_reimpresion')
                ->constrained('trabajo_impresion')
                ->nullOnDelete();
            $table->string('motivo_reimpresion', 255)->nullable()->after('reimpresion_de_id');
            $table->foreignId('usuario_reimpresion_id')
                ->nullable()
                ->after('motivo_reimpresion')
                ->constrained('usuarios')
                ->nullOnDelete();
            $table->string('original_uid', 100)->nullable()->after('usuario_reimpresion_id');
            $table->unique('original_uid', 'uk_trabajo_impresion_original_uid');
        });
    }

    public function down(): void
    {
        Schema::table('trabajo_impresion', function (Blueprint $table) {
            $table->dropUnique('uk_trabajo_impresion_original_uid');
            $table->dropColumn(['original_uid', 'usuario_reimpresion_id', 'motivo_reimpresion', 'reimpresion_de_id', 'es_reimpresion', 'tipo_trabajo']);
        });
    }
};
