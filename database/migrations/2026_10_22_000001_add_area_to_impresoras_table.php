<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impresoras', function (Blueprint $table): void {
            $table->string('area', 20)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('impresoras', function (Blueprint $table): void {
            $table->dropIndex(['area']);
            $table->dropColumn('area');
        });
    }
};
