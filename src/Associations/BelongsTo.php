<?php


namespace Gforces\ActiveRecord\Associations;

use Gforces\ActiveRecord\Association;
use Gforces\ActiveRecord\Base;
use ReflectionProperty;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class BelongsTo extends Association
{
    public function __construct(private string $foreignKey = '')
    {
    }

    public function load(Base $object): Base
    {
        $foreignKey = new ReflectionProperty($object, $this->foreignKey ?: $this->property->getName() . '_id');
        $class = $this->property->getType()->getName();
        return $class::find($foreignKey->getValue($object));
    }

    public function save(Base $object): void
    {
        // TODO: Implement save() method.
    }
}
