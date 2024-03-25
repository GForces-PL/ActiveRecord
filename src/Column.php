<?php


namespace Gforces\ActiveRecord;

use Attribute;
use ReflectionException;
use ReflectionProperty;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column extends PropertyAttribute
{
    public function __construct(public readonly ?bool $autoIncrement = null)
    {
    }

    /**
     * @throws ReflectionException
     */
    public static function getAutoIncrementProperty(string $class): ?ReflectionProperty
    {
        foreach (static::getAll($class) as $column) {
            if ($column->autoIncrement) {
                return $column->property;
            }
        }
        try {
            $column = static::get($class, 'id');
            if ($column->autoIncrement === null) {
                return $column->property;
            }
        } catch (ActiveRecordException) {
        }
        return null;
    }
}
