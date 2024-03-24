<?php
namespace Gforces\ActiveRecord;

use Gforces\ActiveRecord\Connection\Drivers\Driver;
use Gforces\ActiveRecord\Connection\Drivers\Mysql;
use Gforces\ActiveRecord\Connection\Drivers\Pgsql;
use Gforces\ActiveRecord\Connection\Logger;
use Gforces\ActiveRecord\Connection\Statement;
use PDO;
use PDOStatement;

class Connection extends PDO
{
    protected Driver $driver;

    /**
     * @throws ActiveRecordException
     */
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Statement::class]);
        $this->driver = match($this->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'mysql' => new Mysql(),
            'pgsql' => new Pgsql(),
            default => throw new ActiveRecordException('Unsupported database driver')
        };
    }

    /**
     * @param string|string[] $identifier
     * @return string
     */
    public function quoteIdentifier(string|array $identifier): string
    {
        $char = $this->driver->getIdentifierQuotingCharacter();
        return implode('.', array_map(fn($id) => "$char$id$char", (array) $identifier));
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        Logger::logQuery($query);
        return parent::query($query, $fetchMode, ...$fetchModeArgs);
    }

    public function exec($statement): int|false
    {
        Logger::logQuery($statement);
        return parent::exec($statement);
    }
}
