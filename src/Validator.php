<?php


namespace Gforces\ActiveRecord;


use JetBrains\PhpStorm\Pure;
use ReflectionProperty;

abstract class Validator extends PropertyAttribute
{
    protected string|array $message;

    abstract protected function test(Base $object): bool;
    abstract protected function getDefaultMessage(): string;

    /**
     * @throws ValidationException
     */
    public function perform(Base $object): void
    {
        if (!$this->test($object)) {
            throw new ValidationException($this->getMessage() ?: $this->getDefaultMessage());
        }
    }

    #[Pure]
    public function getPropertyName(): string
    {
        return $this->property->getName();
    }

    protected function getMessage(): string
    {
        if (is_callable($this->message)) {
            return call_user_func($this->message);
        }
        return (string) $this->message;
    }
}
