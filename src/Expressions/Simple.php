<?php

namespace Gforces\ActiveRecord\Expressions;

use Gforces\ActiveRecord\Expression;

class Simple extends Expression
{
    public function __construct(private readonly string $expression)
    {
    }

    public function __toString(): string
    {
        return $this->expression;
    }
}
