<?php


namespace Gforces\ActiveRecord\Connection\Providers;


use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Connection;

class Dsn implements Provider
{
    public function __construct(public string $dsn, public ?string $username = null, public ?string $password = '', public ?array $options = null)
    {
    }

    /**
     * @throws ActiveRecordException
     */
    public function getConnection(): Connection
    {
        return new Connection($this->dsn, $this->username, $this->password, $this->options);
    }
}
