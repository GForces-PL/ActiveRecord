<?php

namespace Fixtures;

use Gforces\ActiveRecord\{Base, Column};

class SharedKeyDetails extends Base
{
    #[Column] public int $id;
}
