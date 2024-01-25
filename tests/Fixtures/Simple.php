<?php

namespace Fixtures;

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

class Simple extends Base
{
    #[Column]
    public int $id;
}
