<?php
namespace Gforces\ActiveRecord\Exception;

use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\ValidationException;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Class is renamed', ValidationException::class)]
class Validation extends ActiveRecordException
{
}
