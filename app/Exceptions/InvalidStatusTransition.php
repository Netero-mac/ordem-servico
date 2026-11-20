<?php

namespace App\Exceptions;

use App\Enums\ServiceOrderStatus;
use DomainException;

class InvalidStatusTransition extends DomainException
{
    public static function between(ServiceOrderStatus $from, ServiceOrderStatus $to): self
    {
        return new self("Não é possível mudar o status de \"{$from->label()}\" para \"{$to->label()}\".");
    }
}
