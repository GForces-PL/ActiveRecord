<?php


namespace Gforces\ActiveRecord\Validators;

use Attribute;
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\ValidationContext;
use Gforces\ActiveRecord\Validator;
use JetBrains\PhpStorm\Pure;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Matches extends Validator
{
    public function __construct(
        private readonly string $pattern,
        protected string $message = '',
        protected ValidationContext $context = ValidationContext::always,
    )
    {
    }

    protected function test(Base $object): bool
    {
        $value = $this->property->getValue($object);
        return $this->isValueEmpty($value) || preg_match($this->pattern, $value) === 1;
    }

    #[Pure]
    protected function getDefaultMessage(): string
    {
        return $this->getPropertyName() . " has an invalid format";
    }
}
