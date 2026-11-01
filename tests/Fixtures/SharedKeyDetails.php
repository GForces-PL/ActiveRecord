<?php

namespace Fixtures;

use Gforces\ActiveRecord\{Base, Column};
use Gforces\ActiveRecord\Associations\BelongsTo;

class SharedKeyDetails extends Base
{
    #[Column] public int $id;
    #[BelongsTo('id')] public SharedKeyOwner $owner;
}
