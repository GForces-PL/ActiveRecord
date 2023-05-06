<?php

namespace Gforces\ActiveRecord;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

abstract class PropertyAttribute
{
    public ReflectionProperty $property;

    /**
     * @throws ReflectionException
     */
    public static function getValues(Base $object): array
    {
        $values = [];
        foreach (static::getProperties($object::class) as $name => $property) {
            if ($property->isInitialized($object)) {
                $values[$name] = $property->getValue($object);
            }
        }
        return $values;
    }

    /**
     * @throws ReflectionException
     * @return static[]
     */
    public static function getAll(string $class): array
    {
        $instances = [];
        foreach (static::getProperties($class) as $property) {
            foreach (static::getPropertyAttributes($property) as $attribute) {
                $instance = $attribute->newInstance();
                $instance->property = $property;
                $instances[] = $instance;
            }
        }
        return $instances;
    }

    /**
     * @throws ReflectionException
     * @throws ActiveRecordException
     */
    public static function get(string $class, string $property): static
    {
        $property = new ReflectionProperty($class, $property);
        $attribute = static::getPropertyAttributes($property)[0]
            ?? throw new ActiveRecordException("$class::$property has no " . static::class . " attribute");
        $instance = $attribute->newInstance();
        $instance->property = $property;
        return $instance;
    }

    /**
     * @throws ReflectionException
     * @return array<string, ReflectionProperty>
     */
    public static function getProperties(string $class): array
    {
        $properties = [];
        $classReflection = new ReflectionClass($class);
        foreach ($classReflection->getProperties() as $property) {
            if (static::getPropertyAttributes($property)) {
                $properties[$property->name] = $property;
            }
        }
        return $properties;
    }

    /**
     * @param ReflectionProperty $property
     * @return ReflectionAttribute[]
     */
    private static function getPropertyAttributes(ReflectionProperty $property): array
    {
        return $property->getAttributes(static::class, ReflectionAttribute::IS_INSTANCEOF);
    }
}
