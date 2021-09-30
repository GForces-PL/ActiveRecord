<?php


namespace Gforces\ActiveRecord\Connection\Providers;


use Gforces\ActiveRecord\Connection;

interface Provider
{
    public function getConnection(): Connection;
}
