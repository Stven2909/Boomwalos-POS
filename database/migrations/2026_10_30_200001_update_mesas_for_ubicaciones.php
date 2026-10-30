<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $table): void {
            // Ampliar zona a 40 caracteres para nombres libres.
            $table->string('zona', 40)->default('SALON')->change();

            // Soft deletes.
            $table->softDeletes()->after('activa');
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->string('zona', 20)->default('SALON')->change();
        });
    }
};
