<?php

namespace Fixtures;

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

class Simple extends Base
{
    #[Column]
    public int $id;

    #[Column]
    public string $name;

    #[Column]
    public bool $enabled = false;

    public string $noDbColumn = 'something';
}
