<?php


namespace Gforces\ActiveRecord;

use JetBrains\PhpStorm\Pure;

abstract class Validator extends PropertyAttribute
{
    protected string|\Callable $message;
    protected ValidationContext $context = ValidationContext::always;

    abstract protected function test(Base $object): bool;
    abstract protected function getDefaultMessage(): string;

    /**
     * @throws ValidationException
     */
    public function perform(Base $object): void
    {
        if ($this->shouldHappen($object) && !$this->test($object)) {
            throw new ValidationException($this->getMessage() ?: $this->getDefaultMessage());
        }
    }

    #[Pure]
    public function getPropertyName(): string
    {
        return $this->property->getName();
    }

    protected function shouldHappen(Base $object): bool
    {
        return match ($this->context) {
            ValidationContext::always => true,
            ValidationContext::onCreate => $object->isNew,
            ValidationContext::onUpdate => !$object->isNew,
        };
    }

    protected function getMessage(): string
    {
        if (is_callable($this->message)) {
            return call_user_func($this->message);
        }
        return (string) $this->message;
    }
}
