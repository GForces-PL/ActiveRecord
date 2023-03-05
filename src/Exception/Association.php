<?php
namespace Gforces\ActiveRecord\Exception;

use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\AssociationException;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Class is renamed', AssociationException::class)]
class Association extends ActiveRecordException
{
}
