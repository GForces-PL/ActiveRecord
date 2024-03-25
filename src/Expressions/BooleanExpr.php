<?php

namespace Gforces\ActiveRecord\Expressions;

use Gforces\ActiveRecord\Expression;

class BooleanExpr extends Expression
{
    /**
     * @var Expression[] $expressions
     */
    private array $expressions;

    public function __construct(private readonly BooleanOperator $operator, Expression ...$expressions)
    {
        $this->expressions = $expressions;
    }

    public function __toString(): string
    {
        $operator = strtoupper($this->operator->name);
        $this->setConnection(...$this->expressions);
        $expressionString = implode(" $operator ", $this->expressions);
        return in_array($this->operator, [BooleanOperator::or, BooleanOperator::xor]) ? "($expressionString)" : $expressionString;
    }
}
