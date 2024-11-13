<?php

namespace Gforces\ActiveRecord\Expressions;

use Gforces\ActiveRecord\Expression;

class Between extends Expression
{
    public function __construct(private readonly Expression $expression, private readonly Expression $value1, private readonly Expression $value2)
    {
    }

    public function __toString(): string
    {
        $this->setConnection($this->expression, $this->value1, $this->value2);
        return "$this->expression BETWEEN $this->value1 AND $this->value2";
    }
}
