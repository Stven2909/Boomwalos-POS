<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getName() === 'tenant') {
            return;
        }

        Schema::create('platform_tenants', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('display_name', 150);
            $table->string('status', 30)->default('active')->index();
            $table->string('plan_code', 40)->default('basic');
            $table->string('logo_path')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('primary_color', 20)->nullable();
            $table->string('secondary_color', 20)->nullable();
            $table->string('ticket_header', 150)->nullable();
            $table->text('ticket_footer')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getName() === 'tenant') {
            return;
        }

        Schema::dropIfExists('platform_tenants');
    }
};
