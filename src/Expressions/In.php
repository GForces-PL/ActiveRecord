<?php

namespace Gforces\ActiveRecord\Expressions;

use Gforces\ActiveRecord\Expression;

class In extends Expression
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
        $this->setConection($this->expression, ...$this->values);
        return "$this->expression IN (" . implode(', ', $this->values) . ")";
    }
}
