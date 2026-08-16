<?php

namespace App\Contracts;

use App\Models\TandaPedido;
use App\Models\TrabajoImpresion;

interface KitchenDispatcherInterface
{
    public function dispatch(TandaPedido $batch): ?TrabajoImpresion;
}
