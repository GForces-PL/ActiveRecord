<?php
namespace Fixtures;

use Gforces\ActiveRecord\StringableProperty;

class MutableString implements StringableProperty
{
    public function __construct(public string $value) {}
    public function __toString(): string { return $this->value; }
}
