<?php


namespace Gforces\ActiveRecord\Validators;

use Attribute;
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\ValidationContext;
use Gforces\ActiveRecord\Validator;
use JetBrains\PhpStorm\Pure;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ByteLength extends Validator
{
    public function __construct(
        private readonly ?int $min = null,
        private readonly ?int $max = null,
        protected string $message = '',
        protected ValidationContext $context = ValidationContext::always,
    )
    {
    }

    protected function test(Base $object): bool
    {
        $value = $this->property->getValue($object);
        if ($this->isValueEmpty($value)) {
            return true;
        }
        $length = strlen($value);
        return ($this->min === null || $length >= $this->min)
            && ($this->max === null || $length <= $this->max);
    }

    #[Pure]
    protected function getDefaultMessage(): string
    {
        return 'Invalid length of ' . $this->getPropertyName();
    }
}
