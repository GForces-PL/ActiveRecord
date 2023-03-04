<?php


namespace Gforces\ActiveRecord;

use Gforces\ActiveRecord\Connection\Providers\Provider;
use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Deprecated;
use PDO;
use ReflectionException;

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

    private static bool $createNewObject = true;

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
     * @throws ActiveRecordException
     * @return static[]
     */
    #[ArrayShape([Base::class])]
    public static function findAll(string | array $criteria = '', string $orderBy = 'id ASC', int $limit = null, int $offset = null, string $select = '*'): array
    {
        return static::findAllBySql(static::buildQuery($criteria, $orderBy, $limit, $offset, $select));
    }

    /**
     * @throws ActiveRecordException
     */
    public static function findFirst(string | array $criteria = '', string $orderBy = ''): ?static
    {
        return static::findAllBySql(static::buildQuery($criteria, $orderBy, 1))[0] ?? null;
    }

    /**
     * @throws ActiveRecordException
     */
    public static function findFirstByAttribute(string $attribute, mixed $value, string $orderBy = ''): ?static
    {
        return static::findFirst(static::condition($attribute, $value), $orderBy);
    }

    /**
     * @throws ActiveRecordException
     */
    #[Deprecated(replacement: '%class%::findFirst(%parametersList%)')]
    public static function findFirstByAttributes(array $attributes, string $orderBy = ''): ?static
    {
        return static::findFirst(static::conditions($attributes), $orderBy);
    }

    /**
     * @throws ActiveRecordException
     * @return static[]
     */
    public static function findAllBySql(string $query): array
    {
        self::$createNewObject = false;
        $objects = static::getConnection()->query($query)->fetchAll(PDO::FETCH_CLASS, static::class);
        self::$createNewObject = true;
        return $objects;
    }

    /**
     * @throws ActiveRecordException
     */
    public static function count(string | array $criteria = ''): int
    {
        $query = static::buildQuery($criteria, select: 'COUNT(*)');
        return (int) static::getConnection()->query($query)->fetchColumn();
    }

    /**
     * @throws ActiveRecordException
     */
    public static function insert(array $attributes, bool $ignoreDuplicates = false, string $onDuplicateKeyUpdate = ''): void
    {
        $table = static::getQuotedTableName();
        $columns = implode(',', array_map([static::class, 'quoteIdentifier'], array_keys($attributes)));
        $values = implode(',', static::quoteValues($attributes));
        $command = 'INSERT ' . ($ignoreDuplicates ? 'IGNORE ' : '') . "INTO $table ($columns) VALUES ($values)";
        if ($onDuplicateKeyUpdate) {
            $command .= " ON DUPLICATE KEY UPDATE $onDuplicateKeyUpdate";
        }
        static::getConnection()->exec($command);
    }

    /**
     * @throws ActiveRecordException
     */
    public static function updateAll(array $attributes, array | string $condition = ''): void
    {
        if (empty($attributes)) {
            return;
        }
        $table = static::getQuotedTableName();
        $values = implode(', ', array_map(
            fn($attribute, $value) => static::quoteIdentifier($attribute) . ' = ' . static::quoteValue($value),
            array_keys($attributes), array_values($attributes))
        );
        static::getConnection()->exec("UPDATE $table SET $values" . self::queryWherePart($condition));
    }

    /**
     * @throws ActiveRecordException
     */
    public static function deleteAll(array | string $condition = ''): void
    {
        $table = static::getQuotedTableName();
        static::getConnection()->exec("DELETE FROM $table" . self::queryWherePart($condition));
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
        $class = static::class;
        if (str_starts_with($class, self::$modelsNamespacePrefix)) {
            $class = substr($class, strlen(self::$modelsNamespacePrefix));
        }
        return strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", str_replace('\\', '', $class)));
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
        return static::quoteIdentifier($attribute) . match (gettype($value)) {
            'NULL' => ' IS NULL',
            'array' => ' IN (' . implode(', ', static::quoteValues($value)) . ')',
            default => ' = ' . static::quoteValue($value),
        };
    }

    /**
     * @throws ActiveRecordException
     */
    protected static function conditions(array $attributes, string $operator = 'AND'): string
    {
        return implode(" $operator ",
            array_map(fn($attribute, $value) => static::condition($attribute, $value), array_keys($attributes), array_values($attributes))
        );
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
    protected static function quoteValue(mixed $value): mixed
    {
        return match (gettype($value)) {
            'string' => static::getConnection()->quote($value),
            'boolean' => intval($value),
            'integer', 'double' => $value,
            'NULL' => 'NULL',
            'object' => match (get_class($value)) {
                DbExpression::class => $value,
                default => throw new ActiveRecordException('Invalid value type: ' . get_class($value)),
            },
            default => throw new ActiveRecordException('Invalid value type: ' . gettype($value)),
        };
    }

    /**
     * @throws ActiveRecordException
     */
    protected static function buildQuery(string | array $criteria = '', string $orderBy = '', int $limit = null, int $offset = null, $select = '*', string $joins = ''): string
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
    private static function queryWherePart(string | array $criteria): string
    {
        return self::queryPart('WHERE', is_array($criteria) ? static::conditions($criteria) : $criteria);
    }

    /**
     * @throws ActiveRecordException
     */
    private static function getQuotedTableName(): string
    {
        return static::quoteIdentifier(static::getTableName());
    }

    /**
     * @throws ReflectionException
     */
    public function __construct()
    {
        $this->isNew = self::$createNewObject;
        if (static::$keepAttributeChanges) {
            $this->originalValues = $this->getAttributes();
        }
        foreach (Association::getProperties(static::class) as $property) {
            unset($this->{$property->getName()});
        }
    }

    /**
     * @throws ReflectionException
     */
    public function isValid(): bool
    {
        return count($this->getErrors(true)) == 0;
    }

    /**
     * @throws ReflectionException
     */
    public function getErrors($validate = false): array
    {
        if ($validate) {
            $this->validate();
        }
        return $this->errors;
    }

    /**
     * @throws ReflectionException
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
     * @throws ReflectionException
     * @throws ValidationException
     * @throws ActiveRecordException
     */
    public function save(bool $validate = true): void
    {
        if ($validate && !$this->isValid()) {
            throw new ValidationException('validation failed on save');
        }
        $values = $this->getAttributes();
        if ($this->isNew) {
            static::insert(array_diff_assoc($values, $this->originalValues));
            Column::getAutoIncrementProperty(static::class)?->setValue($this, (int) static::getConnection()->lastInsertId());
            $this->isNew = false;
        } else {
            $this->update(array_diff_assoc($values, $this->originalValues));
        }
        if (static::$keepAttributeChanges) {
            $this->originalValues = $values;
        }
        foreach (Association::getAll(static::class) as $association) {
            $association->save($this);
        }
    }

    /**
     * @throws ActiveRecordException
     * @throws ReflectionException
     */
    public function remove(): void
    {
        if ($this->isNew) {
            return;
        }
        static::deleteAll(PrimaryKey::getValues($this));
    }

    /**
     * @throws ActiveRecordException
     */
    public function __get(string $name)
    {
        try {
            return $this->$name = Association::get(static::class, $name)->load($this);
        } catch (ReflectionException $e) {
            $class = static::class;
            $file = $e->getTrace()[2]['file'];
            $line = $e->getTrace()[2]['line'];
            trigger_error("Undefined property: $class::$$name in $file on line $line and caught", E_USER_WARNING);
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function validate(): void
    {
        foreach (Validator::getAll(static::class) as $validator) {
            try {
                $validator->perform($this);
            } catch (ValidationException $error) {
                $this->errors[$validator->getPropertyName()][] = $error->getMessage();
            }
        }
    }

    /**
     * @throws ReflectionException
     */
    protected function getAttributes(): array
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
            return $this->originalValues[$attribute] !== $this->$attribute;
        }
        try {
            return Column::isPropertyInitialized($this, $attribute);
        } catch (ReflectionException $e) {
            throw new ActiveRecordException('Could nor check changes for invalid attribute', previous: $e);
        }
    }

    /**
     * @throws ActiveRecordException
     * @throws ReflectionException
     */
    private function update(array $attributes): void
    {
        if ($this->isNew) {
            return;
        }
        static::updateAll($attributes, PrimaryKey::getValues($this));
    }
}
