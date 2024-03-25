<?php

namespace Gforces\ActiveRecord;

use Gforces\ActiveRecord\Expressions\BooleanExpr;
use Gforces\ActiveRecord\Expressions\BooleanOperator;
use Gforces\ActiveRecord\Expressions\Identifier;
use Gforces\ActiveRecord\Expressions\Simple;
use Stringable;

abstract class Expression implements Stringable
{
    public Connection $connection;

    public static function now(): Simple
    {
        return new Simple('NOW()');
    }

    public static function and(array $expressions): BooleanExpr
    {
        return new BooleanExpr(BooleanOperator::and, ...self::buildExpressions($expressions));
    }

    public static function or(array $expressions): BooleanExpr
    {
        return new BooleanExpr(BooleanOperator::or, ...self::buildExpressions($expressions));
    }

    public static function xor(array $expressions): BooleanExpr
    {
        return new BooleanExpr(BooleanOperator::xor, ...self::buildExpressions($expressions));
    }

    public static function buildForAttribute(string $attribute, mixed $value): Expression
    {
        if (is_array($value)) {
            $value = PropertyExpression::in($value);
        }
        if (!$value instanceof PropertyExpression) {
            $value = PropertyExpression::eq($value);
        }
        return $value->getExpression(new Identifier($attribute));
    }

    private static function buildExpressions(array $expressions): array
    {
        return array_map(
            fn($key, $value) => is_string($key) ? self::buildForAttribute($key, $value) : ($value instanceof Expression ? $value : new Simple($value)),
            array_keys($expressions),
            array_values($expressions)
        );
    }

    protected function setConnection(Expression ...$expressions): void
    {
        foreach ($expressions as $expression) {
            $expression->connection = $this->connection;
        }
    }
}
