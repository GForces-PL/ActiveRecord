<?php


namespace Gforces\ActiveRecord\Connection\Providers;


use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Connection;

class Dsn implements Provider
{
    public function __construct(public string $dsn, public ?string $username = null, public ?string $password = '', public ?array $options = null)
    {
    }

    /**
     * @throws \ActiveRecord\Exception
     */
    public function getConnection(): Connection
    {
        $connection = new Connection($this->dsn, $this->username, $this->password, $this->options);
        Base::setConnection($connection);
        return $connection;
    }
}
