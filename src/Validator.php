<?php


namespace Gforces\ActiveRecord;


use Gforces\ActiveRecord\Exception\Validation;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use ReflectionProperty;

abstract class Validator
{
    protected string $message;
    protected ReflectionProperty $property;

    #[ArrayShape([Validator::class])]
    public static function getAll(string $class): array
    {
        $validators = [];
        $classReflection = new \ReflectionClass($class);
        foreach ($classReflection->getProperties() as $propertyReflection) {
            foreach ($propertyReflection->getAttributes() as $attribute) {
                if (is_subclass_of($attribute->getName(), static::class)) {
                    $validator = $attribute->newInstance();
                    $validator->property = $propertyReflection;
                    $validators[] = $validator;
                }
            }
        }
        return $validators;
    }

    abstract protected function test(Base $object): bool;
    abstract protected function getDefaultMessage(): string;

    public function perform(Base $object): void
    {
        if (!$this->test($object)) {
            throw new Validation($this->message ?: $this->getDefaultMessage());
        }
    }

    #[Pure]
    public function getPropertyName(): string
    {
        return $this->property->getName();
    }
}
