<?php

namespace Fixtures;

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;
use Gforces\ActiveRecord\PrimaryKey;

class CompositeKey extends Base
{
    #[Column(autoIncrement: false)] public int $id;
    #[Column] #[PrimaryKey] public int $tenant;
    #[Column] #[PrimaryKey] public ?int $number;
    #[Column] public string $name;
}
