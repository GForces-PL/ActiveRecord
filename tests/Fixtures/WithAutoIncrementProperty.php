<?php

namespace Fixtures;

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

class WithAutoIncrementProperty extends Base
{
    #[Column(autoIncrement: false)]
    public int $id;

    #[Column(autoIncrement: true)]
    public int $index;
}
