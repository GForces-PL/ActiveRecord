<?php
namespace Gforces\ActiveRecord\Exception;

use Gforces\ActiveRecord\AssociationException;
use Gforces\ActiveRecord\Exception;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Class is renamed', AssociationException::class)]
class Association extends Exception
{
}
