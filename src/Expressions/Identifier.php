<?php

namespace Gforces\ActiveRecord\Expressions;

use Gforces\ActiveRecord\Expression;

class Identifier extends Expression
{
    /**
     * @param string|string[] $identifier
     */
    public function __construct(private readonly string|array $identifier)
    {
    }

    public function __toString(): string
    {
        return $this->connection->quoteIdentifier($this->identifier);
    }
}
