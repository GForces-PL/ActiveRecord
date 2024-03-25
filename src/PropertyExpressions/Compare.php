<?php

namespace Gforces\ActiveRecord\PropertyExpressions;

use Gforces\ActiveRecord\PropertyExpression;
use Gforces\ActiveRecord\Expression;
use Gforces\ActiveRecord\Expressions\ComparisonOperator;
use Gforces\ActiveRecord\Expressions\Identifier;

class Compare extends PropertyExpression
{
    public function __construct(private readonly ComparisonOperator $operator, private readonly mixed $value)
    {
    }

    public function getExpression(Identifier $attribute): Expression
    {
        return new \Gforces\ActiveRecord\Expressions\Compare($this->operator, $attribute, $this->getValueExpression($this->value));
    }
}
