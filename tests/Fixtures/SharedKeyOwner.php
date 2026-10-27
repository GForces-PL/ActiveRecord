<?php
namespace Fixtures;

use Gforces\ActiveRecord\{Base, Column};
use Gforces\ActiveRecord\Associations\HasOne;

class SharedKeyOwner extends Base
{
    #[Column] public int $id;
    #[Column] public string $name = 'owner';
    #[HasOne(foreignKey: 'id')] public ?SharedKeyDetails $details;
}
