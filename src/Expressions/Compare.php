<?php

namespace Gforces\ActiveRecord\Expressions;

use Gforces\ActiveRecord\Expression;

class Compare extends Expression
{
    private string $operator;

    public function __construct(ComparisonOperator $operator, private readonly Expression $expression1, private readonly Expression $expression2)
    {
        $this->operator = $operator->value;
        if (($this->expression2 instanceof Value && $this->expression2->isNull())) {
            $this->operator = match($operator) {
                ComparisonOperator::eq => 'IS',
                ComparisonOperator::ne => 'IS NOT',
                default => $this->operator,
            };
        }
    }

    public function __toString(): string
    {
        $this->setConection($this->expression1, $this->expression2);
        return "$this->expression1 $this->operator $this->expression2";
    }
}
