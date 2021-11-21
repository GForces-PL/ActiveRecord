<?php

namespace Gforces\ActiveRecord;

use JetBrains\PhpStorm\Pure;

class DbExpression
{
    public function __construct(private string $expression)
    {
    }

    public function __toString(): string
    {
        return $this->expression;
    }

    #[Pure] public static function now(): self
    {
        return new self('NOW()');
    }
}
