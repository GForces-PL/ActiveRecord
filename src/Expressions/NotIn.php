<?php

namespace Gforces\ActiveRecord\Expressions;

use Gforces\ActiveRecord\Expression;

class NotIn extends Expression
{
    /**
     * @var Expression[]
     */
    private array $values;

    public function __construct(private readonly Expression $expression, Expression ...$values)
    {
        $this->values = $values;
    }

    public function __toString(): string
    {
        $this->setConnection($this->expression, ...$this->values);
        return "$this->expression NOT IN (" . implode(', ', $this->values) . ")";
    }
}
