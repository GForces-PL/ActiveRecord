<?php


namespace Gforces\ActiveRecord\Validators;

use Attribute;
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\ValidationContext;
use Gforces\ActiveRecord\Validator;
use JetBrains\PhpStorm\Pure;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Url extends Validator
{
    public function __construct(
        protected string $message = '',
        protected ValidationContext $context = ValidationContext::always,
    )
    {
    }

    #[Pure]
    protected function test(Base $object): bool
    {
        $value = $this->property->getValue($object);
        return $this->isValueEmpty($value) || filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    #[Pure]
    protected function getDefaultMessage(): string
    {
        return $this->getPropertyName() . ' is not valid URL';
    }
}
