<?php


namespace Gforces\ActiveRecord\Validators;

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Validator;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Length extends Validator
{
    const MESSAGE = '';
    const CODE = 1;

    public function __construct(private ?int $min = null, private ?int $max = null, protected string $message = self::MESSAGE)
    {
    }

    protected function test(Base $object): bool
    {
        $value = $this->property->getValue($object);
        return (is_null($this->min) || strlen($value) >= $this->min) && (is_null($this->max) || strlen($value) <= $this->max);
    }

    protected function getDefaultMessage(): string
    {
        return 'Invalid length of ' . $this->getPropertyName();
    }
}
