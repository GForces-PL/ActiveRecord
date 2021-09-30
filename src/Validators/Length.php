<?php


namespace Gforces\ActiveRecord\Validators;

use ActiveRecord\Exception\Validation;
use ActiveRecord\Base;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Length
{
    const MESSAGE = '';
    const CODE = 1;

    public function __construct(private ?int $min = null, private ?int $max = null, private string $message = self::MESSAGE)
    {
    }

    public function perform(string $value)
    {
        if ((!is_null($this->min) && strlen($value) < $this->min) || (!is_null($this->max) && strlen($value) > $this->max)) {
            $error = new Validation($this->message, self::CODE);
        }
    }
}
