<?php


namespace Gforces\ActiveRecord;


use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use ReflectionProperty;

abstract class Association
{
    public ReflectionProperty $property;

    abstract public function load(Base $object): Base|array|null;
    abstract public function save(Base $object): void;

    /**
     * @param string $class
     * @return Association[]
     * @throws \ReflectionException
     */
    #[ArrayShape([Association::class])]
    public static function getAll(string $class): array
    {
        $associations = [];
        $classReflection = new \ReflectionClass($class);
        foreach ($classReflection->getProperties() as $propertyReflection) {
            try {
                $associations[] = self::getAssociationFromProperty($propertyReflection);
            } catch (Exception) {
            }
        }
        return $associations;
    }

    /**
     * @throws \ReflectionException
     * @throws Exception
     */
    public static function get(string $class, string $property): Association
    {
        return self::getAssociationFromProperty(new ReflectionProperty($class, $property));
    }

    private static function getAssociationFromProperty(ReflectionProperty $propertyReflection): Association
    {
        foreach ($propertyReflection->getAttributes() as $attribute) {
            if (is_subclass_of($attribute->getName(), static::class)) {
                $association = $attribute->newInstance();
                $association->property = $propertyReflection;
                return $association;
            }
        }
        throw new Exception("Property $propertyReflection->name is not valid association");
    }

    #[Pure]
    protected function getClassFromArrayShape(ReflectionProperty $property): string
    {
        $attribute = $property->getAttributes(ArrayShape::class)[0] ?? null;
        return $attribute ? $attribute->getArguments()[0][0] : '';
    }
}
