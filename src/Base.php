<?php

namespace Gforces\ActiveRecord;

use BackedEnum;
use DateTime;
use Gforces\ActiveRecord\Connection\Providers\Provider;
use Gforces\ActiveRecord\Expressions\Value;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Deprecated;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Throwable;
use UnitEnum;

class Base
{
    /**
     * defines namespace prefix which is not included in table name
     */
    public static string $modelsNamespacePrefix = '';
    /**
     * Set true if you need access to original values. It also optimises UPDATE queries with only changed values.
     */
    protected static bool $keepAttributeChanges = false;

    private static array $connections = [];

    private static array $connectionProviders = [];

    public bool $isNew = true;

    protected array $errors = [];

    private array $originalValues = [];

    /**
     * @throws ActiveRecordException
     */
    public static function find(int $id): static
    {
        // TODO: custom PK
        return static::findFirstByAttribute('id', $id)
            ?: throw new ActiveRecordException('object with id ' . $id . ' of type ' . static::class . ' not found');
    }

    /**
     * @return static[]
     * @throws ActiveRecordException
     */
    #[ArrayShape([Base::class])]
    public static function findAll(string|array $criteria = '', string $orderBy = '', int $limit = null, int $offset = null, string $select = '*'): array
    {
        return static::findAllBySql(static::buildQuery($criteria, $orderBy, $limit, $offset, $select));
    }

    /**
     * @throws ActiveRecordException
     */
    public static function findFirst(string|array $criteria = '', string $orderBy = '', int $offset = null): ?static
    {
        return static::findAll($criteria, $orderBy, 1, $offset)[0] ?? null;
    }

    /**
     * @throws ActiveRecordException
     */
    public static function findFirstByAttribute(string $attribute, mixed $value, string $orderBy = '', int $offset = null): ?static
    {
        return static::findFirst(static::condition($attribute, $value), $orderBy, $offset);
    }

    /**
     * @throws ActiveRecordException
     */
    #[Deprecated(replacement: '%class%::findFirst(%parametersList%)')]
    public static function findFirstByAttributes(array $attributes, string $orderBy = ''): ?static
    {
        return static::findFirst($attributes, $orderBy);
    }

    /**
     * @return static[]
     * @throws ActiveRecordException
     */
    public static function findAllBySql(string $query): array
    {
        $class = new ReflectionClass(static::class);
        $statement = static::getConnection()->query($query);
        $objects = [];

        try {
            while ($row = $statement->fetchObject()) {
                $object = $class->newInstanceWithoutConstructor();
                foreach ($row as $key => $value) {
                    try {
                        $property = $class->getProperty($key);
                    } catch (ReflectionException) {
                        continue;
                    }
                    $type = $property->getType()->getName();
                    if ($type === 'array') {
                        $property->setValue($object, json_decode($value));
                        continue;
                    }
                    if (is_a($type, BackedEnum::class, true)) {
                        $property->setValue($object, $type::from($value));
                        continue;
                    }
                    if (is_a($type, UnitEnum::class, true)) {
                        $property->setValue($object, $value ? $type::{$value} : null);
                        continue;
                    }
                    if (is_a($type, DateTime::class, true)) {
                        $property->setValue($object, $value ? new DateTime($value) : null);
                        continue;
                    }
                    if (is_a($type, StringableProperty::class, true)) {
                        $property->setValue($object, new $type($value));
                        continue;
                    }
                    $property->setValue($object, $value);
                }
                $object->isNew = false;
                $object->__construct();
                $objects[] = $object;
            }
        } catch (Throwable $e) {
            throw new ActiveRecordException($e->getMessage(), previous: $e);
        }
        return $objects;
    }

    /**
     * @throws ActiveRecordException
     */
    public static function exists(string|array $criteria = ''): bool
    {
        $query = static::buildQuery($criteria);
        return (bool) static::getConnection()->query("SELECT EXISTS($query)")->fetchColumn();
    }

    /**
     * @throws ActiveRecordException
     */
    public static function count(string|array $criteria = '', string $select = '*'): int
    {
        $query = static::buildQuery($criteria, select: "COUNT($select)");
        return (int) static::getConnection()->query($query)->fetchColumn();
    }

    /**
     * @throws ActiveRecordException
     */
    public static function insert(array $attributes, bool $ignoreDuplicates = false, string $onDuplicateKeyUpdate = ''): false|int
    {
        $table = static::getQuotedTableName();
        $columns = implode(',', array_map([static::class, 'quoteIdentifier'], array_keys($attributes)));
        $values = implode(',', static::quoteValues($attributes));
        $command = 'INSERT ' . ($ignoreDuplicates ? 'IGNORE ' : '') . "INTO $table ($columns) VALUES ($values)";
        if ($onDuplicateKeyUpdate) {
            $command .= " ON DUPLICATE KEY UPDATE $onDuplicateKeyUpdate";
        }
        return static::getConnection()->exec($command);
    }

    /**
     * @throws ActiveRecordException
     */
    public static function replaceByDelete(array $attributes): false|int
    {
        $table = static::getQuotedTableName();
        $columns = implode(',', array_map([static::class, 'quoteIdentifier'], array_keys($attributes)));
        $values = implode(',', static::quoteValues($attributes));
        $command = "REPLACE INTO $table ($columns) VALUES ($values)";
        return static::getConnection()->exec($command);
    }

    /**
     * @throws ActiveRecordException
     */
    public static function replaceByUpdate(array $attributes): false|int
    {
        $columns = implode(',', array_map(fn($column) => "$column = VALUES($column)", array_map([static::class, 'quoteIdentifier'], array_keys($attributes))));
        return static::insert($attributes, onDuplicateKeyUpdate: $columns);
    }

    /**
     * @throws ActiveRecordException
     */
    public static function updateAll(array $attributes, array|string $criteria = ''): false|int
    {
        if (empty($attributes)) {
            return 0;
        }
        $table = static::getQuotedTableName();
        $values = implode(
            ', ',
            array_map(
                fn($attribute, $value) => static::quoteIdentifier($attribute) . ' = ' . static::quoteValue($value),
                array_keys($attributes),
                array_values($attributes)
            )
        );
        return static::getConnection()->exec("UPDATE $table SET $values" . self::queryWherePart($criteria));
    }

    /**
     * @throws ActiveRecordException
     */
    public static function deleteAll(array|string $criteria = ''): false|int
    {
        $table = static::getQuotedTableName();
        return static::getConnection()->exec("DELETE FROM $table" . self::queryWherePart($criteria));
    }

    public static function setConnection(Connection $connection): void
    {
        self::$connections[static::class] = $connection;
    }

    /**
     * @throws ActiveRecordException
     */
    public static function getConnection(): Connection
    {
        try {
            return self::$connections[static::class] ??= static::getConnectionProvider()->getConnection();
        } catch (ActiveRecordException $e) {
            if (static::class === self::class) {
                throw $e;
            }
            return Base::getConnection();
        }
    }

    public static function setConnectionProvider(Provider $connectionProvider): void
    {
        self::$connectionProviders[static::class] = $connectionProvider;
    }

    /**
     * @throws ActiveRecordException
     */
    public static function getConnectionProvider(): Provider
    {
        return self::$connectionProviders[static::class] ?? throw new ActiveRecordException('Connection provider is not set');
    }

    public static function getTableName(): string
    {
        static $tableNames;
        return $tableNames[static::class] ??= self::getDefaultTableName();
    }

    /**
     * @throws ActiveRecordException
     */
    protected static function quoteIdentifier($identifier): string
    {
        return static::getConnection()->quoteIdentifier($identifier);
    }

    /**
     * @throws ActiveRecordException
     */
    protected static function condition(string $attribute, mixed $value): string
    {
        $expression = Expression::buildForAttribute($attribute, $value);
        $expression->connection = static::getConnection();
        return (string) $expression;
    }

    /**
     * @throws ActiveRecordException
     */
    protected static function conditions(array $attributes): string
    {
        $expression = Expression::and($attributes);
        $expression->connection = static::getConnection();
        return (string) $expression;
    }

    /**
     * @throws ActiveRecordException
     */
    protected static function quoteValues(array $values): array
    {
        return array_map(fn($value) => static::quoteValue($value), $values);
    }

    /**
     * @throws ActiveRecordException
     */
    protected static function quoteValue(mixed $value): string
    {
        $value = new Value($value);
        $value->connection = static::getConnection();
        return (string) $value;
    }

    /**
     * @throws ActiveRecordException
     */
    protected static function buildQuery(string|array $criteria = '', string $orderBy = '', int $limit = null, int $offset = null, $select = '*', string $joins = ''): string
    {
        $table = static::getQuotedTableName();
        if ($select === '*' && $joins) {
            $select = "$table.*";
        }
        return "SELECT $select FROM $table"
            . self::queryPart('', $joins)
            . self::queryWherePart($criteria)
            . self::queryPart('ORDER BY', $orderBy)
            . self::queryPart('LIMIT', (string) $limit)
            . self::queryPart('OFFSET', (string) $offset);
    }

    private static function queryPart(string $prefix, string $part): string
    {
        return $part ? " $prefix $part" : '';
    }

    /**
     * @throws ActiveRecordException
     */
    private static function queryWherePart(string|array|Expression $criteria): string
    {
        if ($criteria instanceof Expression) {
            $criteria->connection = static::getConnection();
        }
        $part = is_array($criteria) ? static::conditions((array) $criteria) : (string) $criteria;
        return self::queryPart('WHERE', $part);
    }

    /**
     * @throws ActiveRecordException
     */
    private static function getQuotedTableName(): string
    {
        return static::quoteIdentifier(static::getTableName());
    }

    public function __construct()
    {
        if (static::$keepAttributeChanges) {
            $this->originalValues = $this->getValues();
        }
        /** @noinspection PhpUnhandledExceptionInspection */
        foreach (Association::getProperties(static::class) as $property) {
            unset($this->{$property->getName()});
        }
    }

    public function isValid(): bool
    {
        return count($this->getErrors(true)) == 0;
    }

    public function getErrors($validate = false): array
    {
        if ($validate) {
            $this->validate();
        }
        return $this->errors;
    }

    /**
     */
    public function getErrorMessages($validate = false): array
    {
        $messages = [];
        foreach ($this->getErrors($validate) as $errors) {
            $messages = array_merge($messages, array_values($errors));
        }
        return $messages;
    }

    /**
     * @throws ValidationException
     * @throws ActiveRecordException
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function save(bool $validate = true): void
    {
        if ($validate && !$this->isValid()) {
            throw new ValidationException('validation failed on save');
        }
        $values = $this->getValues();
        $changedValues = [];
        foreach ($values as $attribute => $value) {
            if (!array_key_exists($attribute, $this->originalValues) || $value !== $this->originalValues[$attribute]) {
                $changedValues[$attribute] = $value;
            }
        }
        if ($this->isNew) {
            static::insert($changedValues);
            /** @noinspection PhpUnhandledExceptionInspection static::class used */
            Column::getAutoIncrementProperty(static::class)?->setValue($this, (int) static::getConnection()->lastInsertId());
            $this->isNew = false;
        } else {
            static::updateAll($changedValues, PrimaryKey::getValues($this));
        }
        if (static::$keepAttributeChanges) {
            $this->originalValues = $values;
        }
        /** @noinspection PhpUnhandledExceptionInspection static::class used */
        foreach (Association::getAll(static::class) as $association) {
            $association->save($this);
        }
    }

    /**
     * @throws ActiveRecordException
     */
    public function remove(): void
    {
        if ($this->isNew) {
            return;
        }
        static::deleteAll(PrimaryKey::getValues($this));
    }

    public function __get(string $name)
    {
        try {
            return $this->{$name} = Association::get(static::class, $name)->load($this);
        } catch (ActiveRecordException $e) {
            $class = static::class;
            $file = $e->getTrace()[2]['file'];
            $line = $e->getTrace()[2]['line'];
            trigger_error("Undefined property: $class::$$name in $file on line $line", E_USER_WARNING);
        }
    }

    protected function validate(): void
    {
        /* @noinspection PhpUnhandledExceptionInspection Object is instance of Base and exists */
        foreach (Validator::getAll(static::class) as $validator) {
            try {
                $validator->perform($this);
            } catch (ValidationException $error) {
                $this->errors[$validator->getPropertyName()][] = $error->getMessage();
            }
        }
    }

    protected function getValues(): array
    {
        return Column::getValues($this);
    }

    /**
     * @throws ActiveRecordException
     */
    protected function isAttributeChanged($attribute): bool
    {
        if (!static::$keepAttributeChanges) {
            throw new ActiveRecordException('Changes are not stored for this object');
        }
        if (array_key_exists($attribute, $this->originalValues)) {
            return $this->originalValues[$attribute] !== $this->{$attribute};
        }
        try {
            return (new ReflectionProperty($this, $attribute))->isInitialized($this);
        } catch (ReflectionException $e) {
            throw new ActiveRecordException('Could nor check changes for invalid attribute', previous: $e);
        }
    }

    protected function getOriginalValues(): array
    {
        return $this->originalValues;
    }

    private static function getDefaultTableName(): string
    {
        $class = static::class;
        if (str_starts_with($class, self::$modelsNamespacePrefix)) {
            $class = substr($class, strlen(self::$modelsNamespacePrefix));
        }
        return strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", str_replace('\\', '', $class)));
    }
}
