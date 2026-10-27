<?php

namespace Fixtures;

use Gforces\ActiveRecord\{Base, Column};
use Gforces\ActiveRecord\Associations\{HasMany, HasOne};

class SharedKeyOwner extends Base
{
    #[Column] public int $id;
    #[HasOne(foreignKey: 'id')] public SharedKeyDetails $details;
    #[HasOne(foreignKey: 'id')] public ?SharedKeyDetails $optionalDetails;
    #[HasMany(SharedKeyDetails::class, foreignKey: 'id')] public array $children;
}
