<?php

namespace Gforces\ActiveRecord;

use Stringable;

interface Property extends Stringable
{
    public function __construct(string $value);
}
