<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesas', function (Blueprint $table): void {
            $table->string('zona', 20)->default('SALON')->after('numero')->index();
        });
    }

    public function down(): void
    {
        Schema::table('mesas', function (Blueprint $table): void {
            $table->dropColumn('zona');
        });
    }
};
