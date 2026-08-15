<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documento_fiscales', function (Blueprint $table) {
            $table->foreignId('venta_fiscal_pos_id')
                ->nullable()
                ->after('pedido_id')
                ->constrained('ventas_fiscales_pos')
                ->nullOnDelete();
            $table->timestamp('solicitado_at')->nullable()->after('datos_solicitante');
            $table->timestamp('expires_at')->nullable()->after('solicitado_at');
        });
    }

    public function down(): void
    {
        Schema::table('documento_fiscales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venta_fiscal_pos_id');
            $table->dropColumn(['solicitado_at', 'expires_at']);
        });
    }
};
