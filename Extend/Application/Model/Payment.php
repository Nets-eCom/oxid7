<?php

namespace NexiCheckout\Extend\Application\Model;

use NexiCheckout\Core\Module;

class Payment extends Payment_parent
{
    public function isNexiCheckout(): bool
    {
        return Module::isNexiCheckout($this->getId());
    }
}
