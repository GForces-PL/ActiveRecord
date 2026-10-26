<?php
namespace Fixtures;

use Gforces\ActiveRecord\{Base, Column};
use Gforces\ActiveRecord\Associations\HasAndBelongsToMany;

class RelationTag extends Base
{
    #[Column] public int $id;
    #[Column] public string $name = 'tag';
    #[HasAndBelongsToMany(RelationTag::class, 'tag_links', 'source_id', 'target_id')]
    public array $targets;
    #[HasAndBelongsToMany(RelationTag::class, 'tag_links', 'target_id', 'source_id')]
    public array $sources;
}
