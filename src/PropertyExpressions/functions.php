<?php
namespace Gforces\ActiveRecord\PropertyExpressions;

use Gforces\ActiveRecord\PropertyExpression;

function eq(mixed $value): Compare
{
    return PropertyExpression::eq($value);
}

function ne(mixed $value): Compare
{
    return PropertyExpression::ne($value);
}

function gt(mixed $value): Compare
{
    return PropertyExpression::gt($value);
}

function ge(mixed $value): Compare
{
    return PropertyExpression::ge($value);
}

function lt(mixed $value): Compare
{
    return PropertyExpression::lt($value);
}

function le(mixed $value): Compare
{
    return PropertyExpression::le($value);
}

function in(array $values): In
{
    return PropertyExpression::in($values);
}

function notIn(array $values): NotIn
{
    return PropertyExpression::notIn($values);
}
