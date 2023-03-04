<?php

namespace Gforces\ActiveRecord;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class PrimaryKey extends PropertyAttribute
{
    /**
     * @throws \ReflectionException
     * @throws ActiveRecordException
     */
    public static function getValues(Base $object): array
    {
        $values = parent::getValues($object);
        if ($values) {
            return $values;
        }
        try {
            $idColumn = Column::get($object::class, 'id');
            return ['id' => $idColumn->property->getValue($object)];
        } catch (ActiveRecordException|\ReflectionException $e) {
            throw new ActiveRecordException('No primary key found for ' . $object::class);
        }
    }
}
