<?php


namespace Gforces\ActiveRecord\Associations;

use Attribute;
use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Association;
use Gforces\ActiveRecord\Base;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasOne extends Association
{
    /**
     * @param string $foreignKey Custom foreign key.
     */
    public function __construct(private readonly string $foreignKey = '')
    {
    }

    /**
     * @throws ActiveRecordException
     */
    public function load(Base $object): ?Base
    {
        /** @var class-string<Base> $class */
        $class = $this->getRelatedType();
        if ($object->isNew) {
            return $this->getDefaultValue();
        }
        $objectTable = $object::class::getTableName();
        $foreignKey = $this->foreignKey ?: $objectTable . '_id';
        return $class::findFirstByAttribute($foreignKey, $object->id) ?: $this->getDefaultValue();
    }

    /**
     * @throws ActiveRecordException
     */
    public function save(Base $object): void
    {
        if (!$this->property->isInitialized($object)) {
            return;
        }
        $relatedObject = $this->property->getValue($object);
        $objectTable = $object::getTableName();
        $foreignKey = $this->foreignKey ?: $objectTable . '_id';
        if (!$relatedObject) {
            /** @var class-string<Base> $class */
            $class = $this->getRelatedType();
            $class::deleteAll([$foreignKey => $object->id]);
            return;
        }
        $relatedObject->$foreignKey = $object->id;
        $relatedObject->save();
    }

    private function getDefaultValue(): ?Base
    {
        /** @var class-string<Base> $class */
        $class = $this->getRelatedType();
        return $this->property->getType()->allowsNull() ? null : new $class;
    }
}
