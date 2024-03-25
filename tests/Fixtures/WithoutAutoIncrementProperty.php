<?php

namespace Fixtures;

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

class WithoutAutoIncrementProperty extends Base
{
    #[Column(autoIncrement: false)]
    public int $id;

    #[Column]
    public string $name;
}
