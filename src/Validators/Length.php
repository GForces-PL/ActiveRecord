<?php


namespace Gforces\ActiveRecord\Validators;

use Attribute;
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Validator;
use JetBrains\PhpStorm\Pure;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Length extends Validator
{
    public function __construct(private ?int $min = null, private ?int $max = null, protected string|array $message = '')
    {
    }

    protected function test(Base $object): bool
    {
        $value = $this->property->getValue($object);
        return (is_null($this->min) || strlen($value) >= $this->min) && (is_null($this->max) || strlen($value) <= $this->max);
    }

    #[Pure]
    protected function getDefaultMessage(): string
    {
        return 'Invalid length of ' . $this->getPropertyName();
    }
}
