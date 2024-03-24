<?php

namespace Gforces\ActiveRecord\Expressions;

enum ComparisonOperator: string
{
    case eq = '=';
    case ge = '>=';
    case gt = '>';
    case le = '<=';
    case lt = '<';
    case ne = '<>';
}
