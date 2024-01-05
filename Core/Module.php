<?php

namespace NexiCheckout\Core;

class Module
{
    public const ID = 'nexi-checkout';

    public static function isNexiCheckout(string $moduleName): bool
    {
        return self::ID === $moduleName;
    }
}
