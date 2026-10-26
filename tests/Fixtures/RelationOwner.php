<?php
namespace Fixtures;

use Gforces\ActiveRecord\{Base, Column};
use Gforces\ActiveRecord\Associations\{HasMany, HasOne};

class RelationOwner extends Base
{
    #[Column] public int $id;
    #[Column] public string $name = 'owner';
    #[HasMany(RelationChild::class, foreignKey: 'owner_id')] public array $children;
    #[HasMany(RelationStrictChild::class, foreignKey: 'owner_id')] public array $strictChildren;
    #[HasOne(foreignKey: 'owner_id')] public ?RelationChild $optionalChild;
}
