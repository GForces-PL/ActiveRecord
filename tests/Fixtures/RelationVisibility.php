<?php

namespace Fixtures;

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Associations\HasMany;

class RelationVisibility extends Base
{
    #[HasMany(RelationChild::class, foreignKey: 'owner_id')]
    public array $publicChildren;

    #[HasMany(RelationChild::class, foreignKey: 'owner_id')]
    protected array $protectedChildren;

    #[HasMany(RelationChild::class, foreignKey: 'owner_id')]
    private array $privateChildren;

    public function readInDeclaringClass(string $name): array
    {
        return $this->$name;
    }
}
