<?php


namespace Gforces\ActiveRecord\Associations;

use Attribute;
use Gforces\ActiveRecord\Association;
use Gforces\ActiveRecord\Base;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasOne extends Association
{
    /**
     * @param string $foreignKey Custom foreign key.
     * @param bool $createOnEmpty Determines if new instance of related object should be automatically created instead of returning null when not found in the database
     */
    public function __construct(private string $foreignKey = '', private bool $createOnEmpty = false)
    {
    }

    public function load(Base $object): ?Base
    {
        $class = $this->property->getType()->getName();
        if ($object->isNew) {
            return $this->createOnEmpty ? new $class : null;
        }
        $objectTable = $object::class::getTableName();
        $foreignKey = $this->foreignKey ?: $objectTable . '_id';
        return $class::findFirstByAttribute($foreignKey, $object->id) ?: ($this->createOnEmpty ? new $class : null);
    }

    public function save(Base $object): void
    {
        if (!$this->property->isInitialized($object)) {
            return;
        }
        $propertyName = $this->property->getName();
        $relatedObject = $object->$propertyName;
        $objectTable = $object::class::getTableName();
        $foreignKey = $this->foreignKey ?: $objectTable . '_id';
        if (!$relatedObject) {
            $relatedTable = $this->property->getType()->getName()::getTableName();
            $object::getConnection()->exec("DELETE FROM `$relatedTable` WHERE `$foreignKey` = " . $object->id);
            return;
        }
        $relatedObject->$foreignKey = $object->id;
        $relatedObject->save();
    }
}
