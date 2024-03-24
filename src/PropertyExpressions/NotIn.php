<?php

namespace Gforces\ActiveRecord\PropertyExpressions;

use Gforces\ActiveRecord\PropertyExpression;
use Gforces\ActiveRecord\Expression;
use Gforces\ActiveRecord\Expressions\Identifier;
use Override;

class NotIn extends PropertyExpression
{
    public function __construct(private readonly array $values)
    {
    }

    #[Override]
    public function getExpression(Identifier $attribute): Expression
    {
        return new \Gforces\ActiveRecord\Expressions\NotIn(
            $attribute,
            ...array_map(fn($value) => $this->getValueExpression($value), $this->values)
        );
    }
}
