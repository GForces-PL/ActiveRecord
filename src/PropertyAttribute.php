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
     * @param Base $object
     * @return array
     * @noinspection PhpDocMissingThrowsInspection
     */
    public static function getValues(Base $object): array
    {
        $values = [];
        /* @noinspection PhpUnhandledExceptionInspection Object is instance of Base and exists */
        foreach (static::getProperties($object::class) as $name => $property) {
            if ($property->isInitialized($object)) {
                $values[$name] = $property->getValue($object);
            }
        }
        return $values;
    }

    /**
     * @return static[]
     * @throws ReflectionException
     */
    public static function getAll(string|Base $objectOrClass): array
    {
        $instances = [];
        foreach (static::getProperties($objectOrClass) as $property) {
            foreach (static::getPropertyAttributes($property) as $attribute) {
                $instance = $attribute->newInstance();
                $instance->property = $property;
                $instances[] = $instance;
            }
        }
        return $instances;
    }

    /**
     *
     * @throws ActiveRecordException
     */
    public static function get(string|Base $objectOrClass, string $propertyName): static
    {
        $property = null;
        for ($class = $objectOrClass; $property === null && $class; $class = get_parent_class($class)) {
            if (property_exists($class, $propertyName)) {
                $property = new ReflectionProperty($class, $propertyName);
            }
        }
        $class = is_object($objectOrClass) ? $objectOrClass::class : $objectOrClass;
        if ($property === null) {
            throw new ActiveRecordException("$class::$propertyName does not exist");
        }
        $attribute = static::getPropertyAttributes($property)[0]
            ?? throw new ActiveRecordException("$class::$propertyName has no " . static::class . " attribute");
        /**
         * @var static $instance
         */
        $instance = $attribute->newInstance();
        $instance->property = $property;
        return $instance;
    }

    /**
     * @return array<string, ReflectionProperty>
     * @throws ReflectionException
     */
    public static function getProperties(string|Base $objectOrClass): array
    {
        $properties = [];
        for ($class = new ReflectionClass($objectOrClass); $class; $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $property) {
                if (static::getPropertyAttributes($property)) {
                    $properties[$property->name] ??= $property;
                }
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
