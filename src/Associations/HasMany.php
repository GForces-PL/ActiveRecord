<?php


namespace Gforces\ActiveRecord\Associations;

use Attribute;
use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Association;
use Gforces\ActiveRecord\AssociationException;
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasMany extends Association
{
    public function __construct(private readonly string $relatedClass = '', private readonly string $foreignKey = '', private readonly string $orderBy = 'id')
    {
    }

    /**
     * @throws ActiveRecordException
     */
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
        return $relatedClass::findAll([$foreignKey => $objectId], $this->orderBy);
    }

    private function collectionClass(): string
    {
        $class = $this->relatedClass ?: $this->relatedClass();
        if (!is_subclass_of($class, Base::class)) {
            throw new AssociationException('HasMany requires a related model class');
        }
        return $class;
    }

    public function check(Base $object): void
    {
        if (!$this->property->isInitialized($object)) {
            return;
        }
        $class = $this->collectionClass();
        $foreignKey = $this->foreignKey ?: $object::getTableName() . '_id';
        Column::get($class, $foreignKey);
        foreach ($this->property->getValue($object) as $related) {
            if (!$related instanceof $class) {
                throw new AssociationException('Invalid HasMany item; expected ' . $class);
            }
            $this->checkChild($object, $related, $foreignKey);
        }
    }

    public function save(Base $object): void
    {
        if (!$this->property->isInitialized($object)) {
            return;
        }
        $this->check($object);
        $class = $this->collectionClass();
        $foreignKey = $this->foreignKey ?: $object::getTableName() . '_id';
        $ids = [];
        foreach ($this->property->getValue($object) as $related) {
            $related->persistForeignKey($foreignKey, $object->relationId());
            $ids[] = $related->relationId();
        }
        $this->removeMissing($class, $foreignKey, $object->relationId(), $ids);
    }
}
