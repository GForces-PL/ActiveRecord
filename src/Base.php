<?php

namespace Gforces\ActiveRecord;

use BackedEnum;
use Closure;
use DateMalformedStringException;
use DateTime;
use Error;
use Gforces\ActiveRecord\Associations\BelongsTo;
use Gforces\ActiveRecord\Connection\Providers\Provider;
use Gforces\ActiveRecord\Expressions\Value;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Deprecated;
use JsonException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use stdClass;
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

    private static ?AssociationSaveContext $saveContext = null;

    private ?array $persistedPrimaryKey = null;

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
    public static function findAll(string|array $criteria = '', string $orderBy = '', ?int $limit = null, ?int $offset = null, string $select = '*'): array
    {
        return static::findAllBySql(static::buildQuery($criteria, $orderBy, $limit, $offset, $select));
    }

    /**
     * @throws ActiveRecordException
     */
    public static function findFirst(string|array $criteria = '', string $orderBy = '', ?int $offset = null): ?static
    {
        return static::findAll($criteria, $orderBy, 1, $offset)[0] ?? null;
    }

    /**
     * @throws ActiveRecordException
     */
    public static function findFirstByAttribute(string $attribute, mixed $value, string $orderBy = '', ?int $offset = null): ?static
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
                $objects[] = self::createObject($class, $row);
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

    public static function getDatabaseName(): ?string
    {
        return null;
    }

    public static function getTableName(): string
    {
        static $tableNames;
        return $tableNames[static::class] ??= self::getDefaultTableName();
    }

    /**
     * @throws ActiveRecordException
     */
    public static function getQuotedTableName(): string
    {
        $databaseName = static::getDatabaseName();
        return ($databaseName ? static::quoteIdentifier($databaseName) . '.' : '') . static::quoteIdentifier(static::getTableName());
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
    public static function buildQuery(string|array $criteria = '', string $orderBy = '', ?int $limit = null, ?int $offset = null, $select = '*', string $joins = ''): string
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

    /**
     * @throws DateMalformedStringException
     * @throws ReflectionException
     * @throws ActiveRecordException
     */
    private static function createObject(ReflectionClass $class, stdClass $attributes): Base
    {
        /** @var Base $object */
        $object = $class->newInstanceWithoutConstructor();
        foreach ($attributes as $key => $value) {
            try {
                $property = $class->getProperty($key);
            } catch (ReflectionException) {
                continue;
            }
            try {
                $reflectionType = $property->getType();
                if (!$reflectionType instanceof ReflectionNamedType) {
                    throw new ActiveRecordException('A single named property type is required');
                }
                $type = $reflectionType->getName();
                $value = match(true) {
                    $value === null => null,
                    in_array($type, ['int', 'float', 'string', 'bool'], true) => $value,
                    $type === 'array' => self::decodeJsonArray($value, $property),
                    is_a($type, Base::class, true) => self::createObject(new ReflectionClass($type), json_decode($value, flags: JSON_THROW_ON_ERROR)),
                    is_a($type, BackedEnum::class, true) => $type::from($value),
                    is_a($type, UnitEnum::class, true) => !$value && $reflectionType->allowsNull() ? null : $type::{$value},
                    is_a($type, DateTime::class, true) => $value === ''
                        ? throw new ActiveRecordException('Empty date strings are not allowed')
                        : new $type($value),
                    is_a($type, StringableProperty::class, true) => new $type($value),
                    default => throw new ActiveRecordException('Unsupported property type: ' . $type),
                };
                $property->setValue($object, $value);
            } catch (Throwable $e) {
                throw new ActiveRecordException('Cannot hydrate ' . $class->getName() . '::$' . $key . ': ' . $e->getMessage(), previous: $e);
            }
        }
        $object->rememberPrimaryKey((array) $attributes);
        $object->isNew = false;
        $object->__construct();
        return $object;
    }

    /**
     * @throws ActiveRecordException
     * @throws JsonException
     */
    private static function decodeJsonArray(string $json, ReflectionProperty $property): array
    {
        $attribute = $property->getAttributes(JsonArray::class, ReflectionAttribute::IS_INSTANCEOF)[0] ?? null;
        $associative = $attribute?->newInstance()->associative ?? true;
        $decoded = json_decode($json, associative: $associative, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) && !$decoded instanceof stdClass) {
            throw new ActiveRecordException('Expected a JSON array or object');
        }
        return (array) $decoded;
    }

    private static function queryPart(string $prefix, string $part): string
    {
        return $part !== '' ? " $prefix $part" : '';
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

    public function __construct()
    {
        if (static::$keepAttributeChanges) {
            $this->originalValues = self::snapshotValue($this->getValues());
        }
        $this->prepareLazyAssociations();
        foreach (BelongsTo::getAll($this) as $association) {
            $association->remember($this);
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
     * @throws ReflectionException
     */
    public function save(bool $validate = true): void
    {
        $root = self::$saveContext === null;
        self::$saveContext ??= new AssociationSaveContext();
        try {
            self::$saveContext->check($this);
            if (self::$saveContext->enter($this)) {
                $this->persist($validate);
            }
            if ($root) {
                self::$saveContext->finish();
            }
        } finally {
            if ($root) {
                self::$saveContext = null;
            }
        }
    }

    private function persist(bool $validate): void
    {
        $primaryKey = $this->isNew ? null : $this->getPersistedPrimaryKey();
        $belongsTo = BelongsTo::getAll($this);
        foreach ($belongsTo as $association) {
            $association->save($this);
        }
        if ($validate && !$this->isValid()) {
            throw new ValidationException('validation failed on save');
        }
        $values = $this->getValues();
        if ($this->isNew) {
            static::insert($values);
            /** @noinspection PhpUnhandledExceptionInspection static::class used */
            Column::getAutoIncrementProperty(static::class)?->setValue($this, (int) static::getConnection()->lastInsertId());
            $this->rememberPrimaryKey();
            $this->isNew = false;
        } else {
            $changedValues = static::$keepAttributeChanges
                ? array_filter($values, $this->isValueChanged(...), ARRAY_FILTER_USE_BOTH)
                : $values;
            static::updateAll($changedValues, $primaryKey);
        }
        if (static::$keepAttributeChanges) {
            $this->originalValues = self::snapshotValue($this->getValues());
        }
        foreach ($belongsTo as $association) {
            $association->remember($this);
        }
        self::$saveContext->defer($this, Association::getAll($this));
    }

    /**
     * @throws ActiveRecordException
     */
    public function remove(): void
    {
        if ($this->isNew) {
            return;
        }
        static::deleteAll($this->getPersistedPrimaryKey());
    }

    /** @internal Update only a relationship column, preserving other dirty fields. */
    public function persistForeignKey(string $attribute, mixed $value): void
    {
        $property = Column::get($this, $attribute)->property;
        if ($this->isNew) {
            $property->setValue($this, $value);
            $this->save();
            return;
        }
        $key = $this->getPersistedPrimaryKey();
        if (array_key_exists($attribute, $key) && $key[$attribute] !== $value) {
            throw new AssociationException('Cannot change a primary key through a relation');
        }
        if (static::updateAll([$attribute => $value], $key) === false) {
            throw new AssociationException('Failed to update foreign key');
        }
        $property->setValue($this, $value);
        if (static::$keepAttributeChanges) {
            $this->originalValues[$attribute] = self::snapshotValue($value);
        }
        foreach (BelongsTo::getAll($this) as $association) {
            if ($association->foreignKey($this)->getName() === $attribute) {
                $association->invalidate($this);
                $association->remember($this);
            }
        }
    }

    /** @internal Relations require an unchanged, complete persisted identity. */
    public function relationId(): mixed
    {
        if ($this->isNew) {
            throw new AssociationException('Related object has not been saved');
        }
        $this->getPersistedPrimaryKey();
        $id = Column::get($this, 'id')->property;
        if (!$id->isInitialized($this) || $id->getValue($this) === null) {
            throw new AssociationException('Related object requires an id');
        }
        return $id->getValue($this);
    }

    private function rememberPrimaryKey(?array $loadedAttributes = null): void
    {
        try {
            $this->persistedPrimaryKey = PrimaryKey::getValues($this);
            if ($loadedAttributes !== null && array_diff_key($this->persistedPrimaryKey, $loadedAttributes)) {
                $this->persistedPrimaryKey = null;
            }
        } catch (ActiveRecordException) {
            // Partial selections remain readable, but cannot be saved or removed.
            $this->persistedPrimaryKey = null;
        }
    }

    private function getPersistedPrimaryKey(): array
    {
        $values = PrimaryKey::getValues($this);
        if ($this->persistedPrimaryKey === null) {
            throw new ActiveRecordException('No complete persisted primary key for ' . static::class);
        }
        if ($values !== $this->persistedPrimaryKey) {
            throw new ActiveRecordException('Cannot change the primary key of a persisted ' . static::class);
        }
        return $this->persistedPrimaryKey;
    }

    private function prepareLazyAssociations(): void
    {
        foreach (Association::getProperties($this) as $property) {
            if ($property->isInitialized($this)) {
                continue;
            }
            Closure::bind(function () use ($property) {
                unset($this->{$property->getName()});
            }, $this, $property->getDeclaringClass()->getName())();
        }
    }

    public function &__get(string $name)
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['class'] ?? null;
        $class = static::class;
        try {
            $association = Association::get($this, $name);
        } catch (ActiveRecordException) {
            trigger_error("Undefined property: $class::$$name", E_USER_WARNING);
            $result = null;
            return $result;
        }
        if (!$this->isPropertyReadable($association->property, $caller)) {
            throw new ActiveRecordException("Cannot access property $class::$$name");
        }
        $association->property->setValue($this, $association->load($this));
        if ($association instanceof BelongsTo) {
            $association->rememberLoaded($this);
        }
        return Closure::bind(function &() use ($name) { return $this->$name; }, $this, $association->property->getDeclaringClass()->getName())();
    }

    private function isPropertyReadable(ReflectionProperty $property, ?string $caller): bool
    {
        $declaringClass = $property->getDeclaringClass()->getName();
        return $property->isPublic() || $property->isPrivate() && $declaringClass === $caller || $property->isProtected() && is_a($caller, $declaringClass, true);
    }

    protected function validate(): void
    {
        $this->errors = [];
        /* @noinspection PhpUnhandledExceptionInspection Object is instance of Base and exists */
        foreach (Validator::getAll($this) as $validator) {
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
     * @throws JsonException
     */
    protected function isAttributeChanged(string $attribute): bool
    {
        if (!static::$keepAttributeChanges) {
            throw new ActiveRecordException('Changes are not stored for this object');
        }
        $property = Column::get($this, $attribute)->property;
        if (!$property->isInitialized($this)) {
            return array_key_exists($attribute, $this->originalValues);
        }
        return $this->isValueChanged($property->getValue($this), $attribute);
    }

    /**
     * @throws JsonException
     */
    private function isValueChanged(mixed $value, string $attribute): bool
    {
        return !array_key_exists($attribute, $this->originalValues)
            || self::comparisonValue($value)
            !== self::comparisonValue($this->originalValues[$attribute]);
    }

    protected function getOriginalValues(): array
    {
        return self::snapshotValue($this->originalValues);
    }

    /** Compare the representation persisted by Value, rather than object identity. */
    private static function comparisonValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof DateTime => $value->format('Y-m-d H:i:s'),
            $value instanceof StringableProperty => (string) $value,
            is_array($value) => json_encode($value, JSON_THROW_ON_ERROR),
            default => $value,
        };
    }

    /** Keep mutable values independent of the model and of callers inspecting originals. */
    private static function snapshotValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::snapshotValue(...), $value);
        }
        if ($value instanceof DateTime) {
            return clone $value;
        }
        if ($value instanceof StringableProperty) {
            $class = $value::class;
            return new $class((string) $value);
        }
        if ($value instanceof stdClass) {
            $copy = clone $value;
            foreach ($value as $key => $item) {
                $copy->$key = self::snapshotValue($item);
            }
            return $copy;
        }
        return $value;
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
