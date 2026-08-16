<?php

namespace App\Filament\Concerns;

use App\Contracts\EstablishmentContextInterface;
use App\Filament\Pages\EstablishmentSelection;

trait GuardsEstablishment
{
    /**
     * Garantiza una sucursal activa antes de operar la pantalla.
     *
     * Devuelve true si el contexto ya tiene una sucursal activa. Si no la hay:
     * - con varias sucursales accesibles, redirige a la selección de sucursal;
     * - sin ninguna accesible, aborta con 403.
     */
    protected function ensureEstablishment(): bool
    {
        $context = app(EstablishmentContextInterface::class);

        if ($context->idOrNull() !== null) {
            return true;
        }

        if ($context->accessible()->count() > 1) {
            $this->redirect(EstablishmentSelection::getUrl());
        } else {
            abort(403, 'No tienes acceso a ninguna sucursal.');
        }

        return false;
    }
}
