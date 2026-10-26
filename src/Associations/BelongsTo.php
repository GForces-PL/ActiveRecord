<?php

namespace Gforces\ActiveRecord\Associations;

use Attribute;
use Closure;
use Gforces\ActiveRecord\{Association, AssociationException, Base, Column};
use ReflectionProperty;
use WeakMap;

#[Attribute(Attribute::TARGET_PROPERTY)]
class BelongsTo extends Association
{
    /** @var WeakMap<Base, array>|null */
    private static ?WeakMap $states = null;

    public function __construct(private readonly array|string $foreignKey = '')
    {
    }

    public function foreignKey(Base $object): ReflectionProperty
    {
        return Column::get($object::class, $this->callablePropertyValue($this->foreignKey, $object) ?: $this->property->getName() . '_id')->property;
    }

    private function keyState(Base $object): array
    {
        $key = $this->foreignKey($object);
        return $key->isInitialized($object) ? [true, $key->getValue($object)] : [false, null];
    }

    private function identity(?Base $related): ?array
    {
        return $related === null ? null : ($related->isNew ? ['new', spl_object_id($related)] : ['id', $related->relationId()]);
    }

    public function remember(Base $object): void
    {
        self::$states ??= new WeakMap();
        $states = self::$states[$object] ?? [];
        $key = $this->keyState($object);
        $identity = $this->property->isInitialized($object)
            ? $this->identity($this->property->getValue($object))
            : ($key[0] && $key[1] !== null ? ['id', $key[1]] : null);
        $states[$this->property->getName()] = [$key, $identity];
        self::$states[$object] = $states;
    }

    public function rememberLoaded(Base $object): void
    {
        self::$states ??= new WeakMap();
        $states = self::$states[$object] ?? [];
        $name = $this->property->getName();
        $key = $states[$name][0] ?? $this->keyState($object);
        $states[$name] = [$key, $this->identity($this->property->getValue($object))];
        self::$states[$object] = $states;
    }

    /** Whether the relation, rather than a changed FK, controls the assignment. */
    public function usesRelation(Base $object): bool
    {
        if (!$this->property->isInitialized($object)) {
            return false;
        }
        $key = $this->keyState($object);
        [$originalKey, $originalIdentity] = self::$states[$object][$this->property->getName()] ?? [[false, null], null];
        $related = $this->property->getValue($object);
        $identity = $this->identity($related);
        $keyChanged = $key !== $originalKey;
        $relationChanged = $identity !== $originalIdentity;
        if ($keyChanged && !$relationChanged) {
            return false;
        }
        if ($keyChanged && $relationChanged && $key[0]
            && ($related?->isNew || $key[1] !== $related?->relationId())) {
            throw new AssociationException('Conflicting relation and foreign key: ' . $object::class . '::$' . $this->property->getName());
        }
        return true;
    }

    public function relationChanged(Base $object): bool
    {
        $original = self::$states[$object][$this->property->getName()][1] ?? null;
        return $this->property->isInitialized($object)
            && $this->identity($this->property->getValue($object)) !== $original;
    }

    public function check(Base $object): void
    {
        if ($this->usesRelation($object) && $this->property->getValue($object) === null
            && !$this->foreignKey($object)->getType()->allowsNull()) {
            throw new AssociationException('Cannot clear a non-nullable foreign key');
        }
    }

    public function newRelatedObjects(Base $object): array
    {
        return $this->usesRelation($object) ? parent::newRelatedObjects($object) : [];
    }

    public function invalidate(Base $object): void
    {
        $name = $this->property->getName();
        Closure::bind(function () use ($name) { unset($this->$name); }, $object, $this->property->getDeclaringClass()->getName())();
    }

    public function load(Base $object): ?Base
    {
        $id = $this->foreignKey($object)->getValue($object);
        if ($id === null && $this->property->getType()->allowsNull()) {
            return null;
        }
        /** @var class-string<Base> $class */
        $class = $this->getRelatedType();
        return $class::find($id);
    }

    public function save(Base $object): void
    {
        if (!$this->property->isInitialized($object)) {
            return;
        }
        $this->check($object);
        if (!$this->usesRelation($object)) {
            $this->invalidate($object);
            return;
        }
        $related = $this->property->getValue($object);
        if ($related?->isNew) {
            $related->save();
        }
        $this->foreignKey($object)->setValue($object, $related?->relationId());
    }
}
