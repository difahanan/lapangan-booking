<?php

namespace App\Exceptions;

use DomainException;

class SlotAlreadyBooked extends DomainException
{
    public function __construct()
    {
        parent::__construct('Slot sudah dipesan, silakan pilih jam lain.');
    }
}