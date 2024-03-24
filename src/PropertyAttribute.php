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
     * @throws ActiveRecordException
     */
    public static function get(string $class, string $property): static
    {
        try {
            $property = new ReflectionProperty($class, $property);
        } catch (ReflectionException $e) {
            throw new ActiveRecordException($e->getMessage(), $e->getCode(), $e);
        }
        $attribute = static::getPropertyAttributes($property)[0]
            ?? throw new ActiveRecordException("$class::$property has no " . static::class . " attribute");
        $instance = $attribute->newInstance();
        $instance->property = $property;
        return $instance;
    }

    /**
     * @return array<string, ReflectionProperty>
     * @throws ReflectionException
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
