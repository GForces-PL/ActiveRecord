<?php
namespace Gforces\ActiveRecord\Connection;

use PDOStatement;

class Statement extends PDOStatement
{
    public function execute($params = null): bool
    {
        Logger::logQuery($this->queryString, $params);
        return parent::execute($params);
    }
}
