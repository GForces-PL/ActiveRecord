<?php
namespace Fixtures;

use Gforces\ActiveRecord\{Base, Column};

class RelationStrictChild extends Base
{
    #[Column] public int $id;
    #[Column] public int $owner_id;
    #[Column] public string $name = 'strict';
}
