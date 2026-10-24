<?php

namespace Gforces\ActiveRecord;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class JsonArray extends PropertyAttribute
{
    public function __construct(public readonly bool $associative = true)
    {
    }
}
