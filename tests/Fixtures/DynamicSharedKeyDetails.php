<?php

namespace Fixtures;

use Gforces\ActiveRecord\Associations\BelongsTo;
use Gforces\ActiveRecord\Column;

class DynamicSharedKeyDetails extends SharedKeyDetails
{
    public string $keyName = 'id';
    #[Column] public int $owner_id;
    #[BelongsTo([self::class, 'resolveForeignKey'])] public SharedKeyOwner $owner;

    public static function resolveForeignKey(self $object): string
    {
        return $object->keyName;
    }
}
