<?php


namespace Gforces\ActiveRecord\Validators;

use Attribute;
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Validator;
use JetBrains\PhpStorm\Pure;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Required extends Validator
{
    public function __construct(protected string|array $message = '')
    {
    }

    #[Pure]
    protected function test(Base $object): bool
    {
        if (!$this->property->isInitialized($object)) {
            return !$object->isNew;
        }
        return !empty($this->property->getValue($object));
    }

    #[Pure]
    protected function getDefaultMessage(): string
    {
        return $this->getPropertyName() . ' is required';
    }
}
