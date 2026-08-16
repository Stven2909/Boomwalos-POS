<?php

use IlluminateDatabaseMigrationsMigration;
use IlluminateDatabaseSchemaBlueprint;
use IlluminateSupportFacadesSchema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establecimiento_usuario', function (Blueprint $table): void {
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->foreignId('establecimiento_id')->constrained('establecimientos')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['usuario_id', 'establecimiento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establecimiento_usuario');
    }
};
