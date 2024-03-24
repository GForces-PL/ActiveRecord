<?php

namespace Gforces\ActiveRecord\Expressions;

use BackedEnum;
use DateTime;
use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Expression;
use ReflectionEnum;
use ReflectionException;
use UnitEnum;

class Value extends Expression
{
    public function __construct(private readonly mixed $value)
    {
    }

    public function isNull(): bool
    {
        return is_null($this->value instanceof Value ? $this->value->isNull() : $this->value);
    }

    /**
     * @throws ActiveRecordException
     */
    public function __toString(): string
    {
        $value = $this->value;
        return match (gettype($value)) {
            'string' => $this->connection->quote($value),
            'boolean' => (string) intval($value),
            'integer', 'double' => (string) $value,
            'NULL' => 'NULL',
            'object' => $this->quoteObjectValue($value),
            default => throw new ActiveRecordException('Invalid value type: ' . gettype($value)),
        };
    }

    private function quoteObjectValue(object $value): string
    {
        if ($value instanceof Expression) {
            return (string) $value;
        }
        if ($value instanceof BackedEnum) {
            /** @noinspection PhpUnhandledExceptionInspection Value is checked above*/
            $type = (new ReflectionEnum($value))->getBackingType()->getName();
            return match ($type) {
                'int' => (string) $value->value,
                'string' => $this->connection->quote($value->value),
            };
        }
        if ($value instanceof UnitEnum) {
            return $this->connection->quote($value->name);
        }
        if ($value instanceof DateTime) {
            return $this->connection->quote($value->format('Y-m-d H:i:s'));
        }
        throw new ActiveRecordException('Invalid value type: ' . get_class($value));
    }
}
