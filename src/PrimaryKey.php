<?php

namespace Gforces\ActiveRecord;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PrimaryKey extends PropertyAttribute
{
    /**
     * @throws ActiveRecordException
     */
    public static function getValues(Base $object): array
    {
        $properties = static::getProperties($object::class);
        if (!$properties) {
            try {
                $properties = ['id' => Column::get($object::class, 'id')->property];
            } catch (ActiveRecordException $e) {
                throw new ActiveRecordException('No primary key found for ' . $object::class, previous: $e);
            }
        }
        $values = [];
        foreach ($properties as $name => $property) {
            if (!$property->isInitialized($object) || $property->getValue($object) === null) {
                throw new ActiveRecordException('Incomplete primary key for ' . $object::class . ': ' . $name);
            }
            $values[$name] = $property->getValue($object);
        }
        return $values;
    }
}
