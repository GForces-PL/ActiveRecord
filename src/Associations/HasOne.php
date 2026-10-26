<?php


namespace Gforces\ActiveRecord\Associations;

use Attribute;
use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Association;
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

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
    public function check(Base $object): void
    {
        if (!$this->property->isInitialized($object)) {
            return;
        }
        $foreignKey = $this->foreignKey ?: $object::getTableName() . '_id';
        Column::get($this->getRelatedType(), $foreignKey);
        $related = $this->property->getValue($object);
        if ($related !== null) {
            $this->checkChild($object, $related, $foreignKey);
        }
    }

    public function save(Base $object): void
    {
        if (!$this->property->isInitialized($object)) {
            return;
        }
        $this->check($object);
        $related = $this->property->getValue($object);
        $foreignKey = $this->foreignKey ?: $object::getTableName() . '_id';
        $ids = [];
        if ($related !== null) {
            $related->persistForeignKey($foreignKey, $object->relationId());
            $ids[] = $related->relationId();
        }
        $this->removeMissing($this->getRelatedType(), $foreignKey, $object->relationId(), $ids);
    }

    private function getDefaultValue(): ?Base
    {
        /** @var class-string<Base> $class */
        $class = $this->getRelatedType();
        return $this->property->getType()->allowsNull() ? null : new $class;
    }
}
