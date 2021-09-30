<?php


namespace Gforces\ActiveRecord\Connection\Drivers;


interface Driver
{
    public function getIdentifierQuotingCharacter(): string;
}
