<?php


namespace Gforces\ActiveRecord;

use Gforces\ActiveRecord\Connection\Providers\Provider;
use Gforces\ActiveRecord\Exception\Validation;
use JetBrains\PhpStorm\ArrayShape;
use PDO;

class Base
{
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

    public static function find(int $id): static
    {
        // TODO: custom PK
        return static::findFirstByAttribute('id', $id)
            ?: throw new Exception('object with id ' . $id . ' of type ' . static::class . ' not found');
    }

    #[ArrayShape([Base::class])]
    public static function findAll(string $criteria = '', string $orderBy = 'id ASC', int $limit = null, int $offset = null, string $select = '*'): array
    {
        return static::findAllBySql(static::buildQuery($criteria, $orderBy, $limit, $offset, $select));
    }

    public static function findFirst(string $criteria = '', string $orderBy = ''): ?static
    {
        return static::findAllBySql(static::buildQuery($criteria, $orderBy, 1))[0] ?? null;
    }

    public static function findFirstByAttribute(string $attribute, mixed $value, string $orderBy = ''): ?static
    {
        return static::findFirst(self::condition($attribute, $value), $orderBy);
    }

    public static function findFirstByAttributes(array $attributes, string $orderBy = ''): ?static
    {
        return static::findFirst(self::conditions($attributes), $orderBy);
    }

    public static function findAllBySql(string $query): array
    {
        self::$createNewObject = false;
        $objects = static::getConnection()->query($query)->fetchAll(PDO::FETCH_CLASS, static::class);
        self::$createNewObject = true;
        return $objects;
    }

    public static function count(string $criteria = ''): int
    {
        $query = static::buildQuery($criteria, select: 'COUNT(*)');
        return (int) static::getConnection()->query($query)->fetchColumn();
    }

    public static function insert(array $attributes, bool $ignoreDuplicates = false): void
    {
        $table = self::getQuotedTableName();
        $columns = implode(',', array_map([static::class, 'quoteIdentifier'], array_keys($attributes)));
        $values = implode(',', self::quoteValues($attributes));
        static::getConnection()->exec('INSERT ' . ($ignoreDuplicates ? 'IGNORE ' : '') . "INTO $table ($columns) VALUES ($values)");
    }

    public static function updateAll(array $attributes, string $condition = ''): void
    {
        if (empty($attributes)) {
            return;
        }
        $table = self::getQuotedTableName();
        $values = implode(', ', array_map(fn($attribute, $value) => static::quoteIdentifier($attribute) . ' = ' . self::quoteValue($value), array_keys($attributes), array_values($attributes)));
        static::getConnection()->exec("UPDATE $table SET $values" . self::queryPart('WHERE', $condition));
    }

    public static function setConnection(Connection $connection): void
    {
        self::$connections[static::class] = $connection;
    }

    /**
     * @throws Exception
     */
    public static function getConnection(): Connection
    {
        return self::$connections[static::class] ?? self::$connections[self::class] ?? self::$connections[static::class] = static::getConnectionProvider()->getConnection();
    }

    public static function setConnectionProvider(Provider $connectionProvider): void
    {
        self::$connectionProviders[static::class] = $connectionProvider;
    }

    /**
     * @throws Exception
     */
    public static function getConnectionProvider(): Provider
    {
        return self::$connectionProviders[static::class] ?? self::$connectionProviders[self::class] ?? throw new Exception('Connection provider is not set');
    }

    public static function getTableName(): string
    {
        return strtolower(preg_replace('/(.)([A-Z])/', "$1_$2", static::class));
    }

    protected static function quoteIdentifier($identifier): string
    {
        return static::getConnection()->quoteIdentifier($identifier);
    }

    protected static function condition(string $attribute, mixed $value): string
    {
        return self::quoteIdentifier($attribute) . match(gettype($value)) {
            'boolean' => ' = ' . intval($value),
            'integer' => ' = ' . $value,
            'NULL' => ' IS NULL',
            'array' => ' IN (' . implode(', ', self::quoteValues($value)) . ')',
            default => ' = ' . self::getConnection()->quote($value)
        };
    }

    protected static function conditions(array $attributes, string $operator = 'AND'): string
    {
        return implode(" $operator ", array_map(fn($attribute, $value) => static::condition($attribute, $value), array_keys($attributes), array_values($attributes)));
    }

    protected static function quoteValues(array $values): array
    {
        return array_map(fn($value) => self::quoteValue($value), $values);
    }

    protected static function quoteValue(mixed $value): mixed
    {
        return match(gettype($value)) {
            'boolean' => intval($value),
            'integer' => $value,
            'NULL' => 'NULL',
            default => self::getConnection()->quote($value)
        };
    }

    protected static function buildQuery(string $criteria = '', string $orderBy = '', int $limit = null, int $offset = null, $select = '*', string $joins = ''): string
    {
        $table = self::getQuotedTableName();
        return "SELECT $select FROM $table"
            . self::queryPart('', $joins)
            . self::queryPart('WHERE', $criteria)
            . self::queryPart('ORDER BY', $orderBy)
            . self::queryPart('LIMIT', (string) $limit)
            . self::queryPart('OFFSET', (string) $offset);
    }

    private static function queryPart(string $prefix, string $part): string
    {
        return $part ? " $prefix $part" : '';
    }

    private static function getQuotedTableName(): string
    {
        return self::quoteIdentifier(self::getTableName());
    }

    public function __construct()
    {
        $this->isNew = self::$createNewObject;
        if (static::$keepAttributeChanges) {
            $this->originalValues = $this->getAttributes();
        }
        foreach (Association::getAll(static::class) as $association) {
            $propertyName = $association->property->getName();
            unset($this->{$propertyName});
        }
    }

    public function isValid(): bool
    {
        $this->validate();
        return count($this->errors) == 0;
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

    public function save(bool $validate = true): void
    {
        if ($validate && !$this->isValid()) {
            throw new Exception\Validation('validation failed on save');
        }
        $values = $this->getAttributes();
        if ($this->isNew) {
            static::insert($values);
            $this->id = (int) self::getConnection()->lastInsertId();
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

    public function remove(): void
    {
        if ($this->isNew) {
            return;
        }
        $table = self::getQuotedTableName();
        self::getConnection()->exec("DELETE FROM $table WHERE id = " . self::getConnection()->quote($this->id));
    }

    public function __get(string $name)
    {
        try {
            return $this->$name = Association::get(static::class, $name)->load($this);
        } catch (\ReflectionException $e) {
            $class = static::class;
            $file = $e->getTrace()[2]['file'];
            $line = $e->getTrace()[2]['line'];
            trigger_error("Undefined property: $class::$$name in $file on line $line and caught", E_USER_WARNING);
        }
    }

    protected function validate(): void
    {
        foreach (Validator::getAll(static::class) as $validator) {
            try {
                $validator->perform($this);
            } catch (Validation $error) {
                $this->errors[$validator->getPropertyName()][] = $error->getMessage();
            }
        }
    }

    protected function getAttributes(): array
    {
        return Column::getObjectValues($this);
    }

    protected function isAttributeChanged($attribute): bool
    {
        if (!array_key_exists($attribute, $this->originalValues)) {
            throw new Exception('Invalid attribute or changes are not stored for this object');
        }
        return $this->originalValues[$attribute] !== $this->$attribute;
    }

    private function update(array $attributes): void
    {
        if ($this->isNew) {
            return;
        }
        static::updateAll($attributes, static::condition('id', $this->id));
    }
}
