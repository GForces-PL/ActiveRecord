<?php


namespace Gforces\ActiveRecord;

use Gforces\ActiveRecord\Connection\Providers\Provider;
use Gforces\ActiveRecord\Exception\Validation;
use JetBrains\PhpStorm\ArrayShape;
use PDO;

class Base
{
    protected static Connection $connection;

    protected static Provider $connectionProvider;

    /**
     * Set true if you need access to original values. It also optimises UPDATE queries with only changed values.
     */
    protected static bool $keepAttributeChanges = false;

    private static bool $createNewObject = true;

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
        $criteria = implode(' AND ', array_map(
            fn(string $attribute) => self::condition($attribute, $attributes[$attribute]),
            array_keys($attributes))
        );
        return static::findFirst($criteria, $orderBy);
    }

    public static function findAllBySql(string $query, array $params = []): array
    {
        self::$createNewObject = false;
        $statement = static::getConnection()->prepare($query);
        $statement->execute($params);
        $objects = $statement->fetchAll(PDO::FETCH_CLASS, static::class);
        self::$createNewObject = true;
        return $objects;
    }

    public static function count(string $criteria = ''): int
    {
        $query = static::buildQuery($criteria, select: 'count(*)');
        return (int) static::getConnection()->query($query)->fetchColumn();
    }

    public static function insert(array $attributes, bool $ignoreDuplicates = false): void
    {
        $table = static::getTableName();
        $columns = implode(',', array_map([static::class, 'quoteIdentifier'], array_keys($attributes)));
        $values = implode(',', array_fill(0, count($attributes), '?'));
        static::getConnection()->prepare('INSERT ' . ($ignoreDuplicates ? 'IGNORE ' : '') .  "INTO $table ($columns) VALUES ($values)")->execute(self::getBindVariables($attributes));
    }

    public static function updateAll(array $attributes, string $condition = ''): void
    {
        if (empty($attributes)) {
            return;
        }
        $table = static::getTableName();
        $columns = implode(',', array_map(fn($column) => static::quoteIdentifier($column) . ' = ?', array_keys($attributes)));
        static::getConnection()->prepare("UPDATE $table SET $columns" . self::queryPart('WHERE', $condition))->execute(self::getBindVariables($attributes));
    }

    public static function setConnection(Connection $connection): void
    {
        static::$connection = $connection;
    }

    /**
     * @throws Exception
     */
    public static function getConnection(): Connection
    {
        return static::$connection ?? self::$connection ?? static::$connection = static::getConnectionProvider()->getConnection();
    }

    public static function setConnectionProvider(Provider $connectionProvider): void
    {
        static::$connectionProvider = $connectionProvider;
    }

    /**
     * @throws Exception
     */
    public static function getConnectionProvider(): Provider
    {
        return static::$connectionProvider ?? throw new Exception('Connection provider not set up');
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
            default => ' = ' . self::getConnection()->quote($value)
        };
    }

    protected static function buildQuery(string $criteria = '', string $orderBy = '', int $limit = null, int $offset = null, $select = '*', string $joins = ''): string
    {
        $table = static::getTableName();
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

    private static function getBindVariables(array $values): array
    {
        return array_map(fn($value) => is_bool($value) ? (int) $value : $value, array_values($values));
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
        $table = static::getTableName();
        $query = "DELETE FROM `$table` WHERE id = ?";
        self::getConnection()->prepare($query)->execute([$this->id]);
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
