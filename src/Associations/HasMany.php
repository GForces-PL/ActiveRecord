<?php


namespace Gforces\ActiveRecord\Associations;

use Gforces\ActiveRecord\Association;
use Gforces\ActiveRecord\Base;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class HasMany extends Association
{
    public function __construct(private string $relatedClass = '', private string $foreignKey = '', private string $orderBy = 'id')
    {
    }

    public function load(Base $object): array
    {
        if ($object->isNew) {
            return [];
        }
        $relatedClass = $this->relatedClass ?: $this->getClassFromArrayShape($this->property);
        if (!is_subclass_of($relatedClass, Base::class)) {
            return [];
        }
        $objectTable = $object::class::getTableName();
        $foreignKey = $this->foreignKey ?: $objectTable . '_id';
        $objectId = $object->id;
        return $relatedClass::findAll("`$foreignKey` = $objectId", $this->orderBy);
    }

    public function save(Base $object): void
    {
        // TODO: Implement save() method.
    }
}
