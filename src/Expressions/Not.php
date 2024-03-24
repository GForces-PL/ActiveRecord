<?php

namespace Gforces\ActiveRecord\Expressions;

use Gforces\ActiveRecord\Expression;

class Not extends Expression
{
    /**
     * @param Expression $expression
     */
    public function __construct(private readonly Expression $expression)
    {
    }

    public function __toString(): string
    {
        $this->setConection($this->expression);
        return "NOT $this->expression";
    }
}
