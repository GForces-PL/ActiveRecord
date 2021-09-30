<?php


namespace Gforces\ActiveRecord;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    private static array $classProperties = [];

    public static function getObjectValues(Base $object): array
    {
        $class = $object::class;
        $properties = self::$classProperties[$class] ??= self::getClassProperties($class);
        $values = [];
        foreach ($properties as $name => $property) {
            if ($property->isInitialized($object)) {
                $values[$name] = $property->getValue($object);
            }
        }
        return $values;
    }

    private static function getClassProperties(string $class): array
    {
        $class = new \ReflectionClass($class);
        $properties = [];
        foreach ($class->getProperties() as $property) {
            $columnAttribute = $property->getAttributes(Column::class)[0] ?? null;
            if ($columnAttribute) {
                $property->setAccessible(true);
                $properties[$property->getName()] = $property;
            }
        }
        return $properties;
    }
}
