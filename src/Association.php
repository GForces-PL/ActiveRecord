<?php


namespace Gforces\ActiveRecord;


use Gforces\ActiveRecord\Associations\BelongsTo;
use JetBrains\PhpStorm\ArrayShape;
use ReflectionProperty;
use function Gforces\ActiveRecord\PropertyExpressions\notIn;

abstract class Association extends PropertyAttribute
{
    public ReflectionProperty $property;

    abstract public function load(Base $object): Base|array|null;
    abstract public function save(Base $object): void;

    /** Validate initialized relation data before any cascading writes. */
    public function check(Base $object): void
    {
    }

    /** @return Base[] */
    public function newRelatedObjects(Base $object): array
    {
        if (!$this->property->isInitialized($object)) {
            return [];
        }
        $value = $this->property->getValue($object);
        $items = is_array($value) ? $value : [$value];
        return array_filter($items, fn($item) => $item instanceof Base && $item->isNew);
    }

    protected function relatedClass(): string
    {
        $class = $this->getClassFromArrayShape($this->property);
        if (!is_subclass_of($class, Base::class)) {
            throw new AssociationException('Missing related model class for ' . $this->property->getName());
        }
        return $class;
    }

    protected function checkChild(Base $owner, Base $child, string $foreignKey): void
    {
        Column::get($child::class, $foreignKey);
        if (!$child->isNew) {
            $child->relationId();
        }
        foreach (BelongsTo::getAll($child) as $inverse) {
            if ($inverse->foreignKey($child)->getName() !== $foreignKey) {
                continue;
            }
            $inverse->check($child);
            if (!$inverse->property->isInitialized($child) || !$inverse->usesRelation($child)) {
                continue;
            }
            $target = $inverse->property->getValue($child);
            if ($target === $owner || ($target !== null && !$target->isNew && !$owner->isNew
                && $target::class === $owner::class && $target->relationId() === $owner->relationId())) {
                continue;
            }
            // An unchanged cached inverse may be invalidated when moving a child.
            if ($inverse->relationChanged($child)) {
                throw new AssociationException('Conflicting inverse relation: ' . $inverse->property->getName());
            }
        }
    }

    /**
     * @param class-string<Base> $class
     * @throws ActiveRecordException
     * @throws AssociationException
     */
    protected function removeMissing(string $class, string $foreignKey, mixed $ownerId, array $ids): void
    {
        // Keep both predicates even when the foreign key is also named "id".
        $criteria = [[$foreignKey => $ownerId], ['id' => notIn($ids)]];
        $property = Column::get($class, $foreignKey)->property;
        $result = $property->getType()->allowsNull()
            ? $class::updateAll([$foreignKey => null], $criteria)
            : $class::deleteAll($criteria);
        if ($result === false) {
            throw new AssociationException('Failed to remove missing related records');
        }
    }

    protected function getClassFromArrayShape(ReflectionProperty $property): string
    {
        $attribute = $property->getAttributes(ArrayShape::class)[0] ?? null;
        return $attribute ? $attribute->getArguments()[0][0] : '';
    }

    /**
     * @return class-string<Base>
     */
    protected function getRelatedType(): string
    {
        return $this->property->getType()->getName();
    }

    protected function callablePropertyValue(array|string $propertyValue, Base $object): string
    {
        if (is_callable($propertyValue)) {
            return call_user_func($propertyValue, $object);
        }
        return (string) $propertyValue;
    }

}
