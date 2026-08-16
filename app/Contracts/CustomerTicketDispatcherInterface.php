<?php

namespace App\Contracts;

use App\Application\Printing\QueueTicketResult;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\User;

interface CustomerTicketDispatcherInterface
{
    public function dispatch(Pedido $pedido, Pago $pago, User $actor): QueueTicketResult;
}
