<?php

namespace Gforces\ActiveRecord\Expressions;

use BackedEnum;
use DateTime;
use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Expression;
use Gforces\ActiveRecord\StringableProperty;
use ReflectionEnum;
use UnitEnum;

class Value extends Expression
{
    public function __construct(private readonly mixed $value)
    {
    }

    public function isNull(): bool
    {
        return $this->value instanceof Value ? $this->value->isNull() : is_null($this->value);
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
            'array' => $this->connection->quote(json_encode($value)),
            'object' => $this->quoteObjectValue($value),
            default => throw new ActiveRecordException('Invalid value type: ' . gettype($value)),
        };
    }

    /**
     * @throws ActiveRecordException
     * @noinspection PhpDocMissingThrowsInspection
     */
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
        if ($value instanceof StringableProperty) {
            return $this->connection->quote((string) $value);
        }
        throw new ActiveRecordException('Invalid value type: ' . get_class($value));
    }
}
