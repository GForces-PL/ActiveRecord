<?php

namespace Gforces\ActiveRecord\PropertyExpressions;

use Gforces\ActiveRecord\PropertyExpression;
use Gforces\ActiveRecord\Expression;
use Gforces\ActiveRecord\Expressions\Identifier;

class Between extends PropertyExpression
{
    public function __construct(private readonly mixed $value1, private readonly mixed $value2)
    {
    }

    public function getExpression(Identifier $attribute): Expression
    {
        return new \Gforces\ActiveRecord\Expressions\Between(
            $attribute,
            $this->getValueExpression($this->value1),
            $this->getValueExpression($this->value2)
        );
    }
}
