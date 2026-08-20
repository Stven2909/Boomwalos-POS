<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impresoras', function (Blueprint $table): void {
            $table->unsignedSmallInteger('puerto')->nullable()->default(9100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('impresoras', function (Blueprint $table): void {
            $table->unsignedSmallInteger('puerto')->nullable(false)->default(9100)->change();
        });
    }
};
