<?php

namespace Gforces\ActiveRecord;

use Gforces\ActiveRecord\PropertyExpressions\Compare;
use Gforces\ActiveRecord\PropertyExpressions\In;
use Gforces\ActiveRecord\PropertyExpressions\NotIn;
use Gforces\ActiveRecord\Expressions\ComparisonOperator;
use Gforces\ActiveRecord\Expressions\Identifier;
use Gforces\ActiveRecord\Expressions\Value;

abstract class PropertyExpression
{
    public static function eq(mixed $value): Compare
    {
        return new Compare(ComparisonOperator::eq, $value);
    }

    public static function ne(mixed $value): Compare
    {
        return new Compare(ComparisonOperator::ne, $value);
    }

    public static function gt(mixed $value): Compare
    {
        return new Compare(ComparisonOperator::gt, $value);
    }

    public static function ge(mixed $value): Compare
    {
        return new Compare(ComparisonOperator::ge, $value);
    }

    public static function lt(mixed $value): Compare
    {
        return new Compare(ComparisonOperator::lt, $value);
    }

    public static function le(mixed $value): Compare
    {
        return new Compare(ComparisonOperator::le, $value);
    }

    public static function in(array $values): In
    {
        return new In($values);
    }

    public static function notIn(array $values): NotIn
    {
        return new NotIn($values);
    }
    abstract public function getExpression(Identifier $attribute): Expression;

    protected function getValueExpression(mixed $value): Expression
    {
        return $value instanceof Expression ? $value : new Value($value);
    }
}
