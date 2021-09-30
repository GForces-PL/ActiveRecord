<?php

namespace Gforces\ActiveRecord\Connection\Drivers;

class Pgsql implements Driver
{
    public function getIdentifierQuotingCharacter(): string
    {
        return '"';
    }
}
