<?php

use IlluminateDatabaseMigrationsMigration;
use IlluminateDatabaseSchemaBlueprint;
use IlluminateSupportFacadesSchema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getName() === 'tenant') {
            return;
        }

        Schema::create('platform_tenant_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->string('driver', 30);
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('database');
            $table->string('username')->nullable();
            $table->text('password')->nullable();
            $table->string('unix_socket')->nullable();
            $table->json('options')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getName() === 'tenant') {
            return;
        }

        Schema::dropIfExists('platform_tenant_connections');
    }
};
