<?php

namespace Gforces\ActiveRecord\Connection\Drivers;

class Mysql implements Driver
{
    public function getIdentifierQuotingCharacter(): string
    {
        return '`';
    }
}
