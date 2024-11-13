<?php


namespace Gforces\ActiveRecord;


use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use ReflectionProperty;

abstract class Association extends PropertyAttribute
{
    public ReflectionProperty $property;

    abstract public function load(Base $object): Base|array|null;
    abstract public function save(Base $object): void;

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
}
