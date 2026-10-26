<?php
namespace Fixtures;

use Gforces\ActiveRecord\{Base, Column};
use Gforces\ActiveRecord\Associations\BelongsTo;

class RelationChild extends Base
{
    #[Column] public int $id;
    #[Column] private ?int $owner_id;
    #[Column] public string $name = 'child';
    #[BelongsTo] public ?RelationOwner $owner;

    public function setOwnerId(?int $id): void { $this->owner_id = $id; }
    public function ownerId(): ?int { return $this->owner_id; }
}
