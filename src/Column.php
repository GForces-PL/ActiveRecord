<?php


namespace Gforces\ActiveRecord;

use Attribute;
use ReflectionException;
use ReflectionProperty;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column extends PropertyAttribute
{
    /**
     * @throws ReflectionException
     */
    public static function isPropertyInitialized(Base $object, string $propertyName): bool
    {
        return (new ReflectionProperty($object, $propertyName))->isInitialized($object);
    }
}
