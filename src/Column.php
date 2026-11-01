<?php


namespace Gforces\ActiveRecord;

use Attribute;
use Gforces\ActiveRecord\Associations\BelongsTo;
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
     * @throws ActiveRecordException
     */
    public static function getAutoIncrementProperty(string|Base $objectOrClass): ?ReflectionProperty
    {
        foreach (static::getAll($objectOrClass) as $column) {
            if ($column->autoIncrement) {
                return $column->property;
            }
        }
        try {
            $column = static::get($objectOrClass, 'id');
        } catch (ActiveRecordException) {
            return null;
        }
        if ($column->autoIncrement === null) {
            foreach (BelongsTo::getAll($objectOrClass) as $association) {
                if ($association->foreignKey($objectOrClass)->getName() === 'id') {
                    return null;
                }
            }
            return $column->property;
        }
        return null;
    }
}
