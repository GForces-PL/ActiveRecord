<?php

namespace Gforces\ActiveRecord;

use Stringable;

interface StringableProperty extends Stringable
{
    public function __construct(string $value);
}
