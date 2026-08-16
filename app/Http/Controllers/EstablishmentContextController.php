<?php

namespace App\Http\Controllers;

use App\Contracts\EstablishmentContextInterface;
use App\Models\Establecimiento;
use Illuminate\Http\RedirectResponse;

class EstablishmentContextController extends Controller
{
    public function __invoke(Establecimiento $establecimiento, EstablishmentContextInterface $context): RedirectResponse
    {
        $context->set((int) $establecimiento->getKey());

        return back()->with('status', 'Sucursal activa actualizada.');
    }
}
