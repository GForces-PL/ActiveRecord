<?php


namespace Gforces\ActiveRecord\Associations;

use Attribute;
use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Association;
use Gforces\ActiveRecord\AssociationException;
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Connection;
use Gforces\ActiveRecord\Expressions\Value;
use Gforces\ActiveRecord\ValidationException;
use PDO;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasAndBelongsToMany extends Association
{
    public function __construct(private readonly string $relatedClass = '', private readonly array|string $intermediateTable = '', private readonly array|string $objectForeignKey = '', private readonly array|string $relatedForeignKey = '')
    {
    }

    /**
     * @throws ActiveRecordException
     */
    public function load(Base $object): array
    {
        if ($object->isNew) {
            return [];
        }
        $relatedClass = $this->relatedClass ?: $this->getClassFromArrayShape($this->property);
        if (!is_subclass_of($relatedClass, Base::class)) {
            return [];
        }
        $objectTable = $object::class::getTableName();
        $relatedTable = $relatedClass::getTableName();
        $intermediateTable = $this->getIntermediateTableName($object, $objectTable, $relatedTable);
        $objectForeignKey = $this->callablePropertyValue($this->objectForeignKey, $object) ?: ($objectTable . '_id');
        $relatedForeignKey = $this->callablePropertyValue($this->relatedForeignKey, $object) ?: ($relatedTable . '_id');

        $relatedQuotedTable = $relatedClass::getQuotedTableName();

        $connection = $relatedClass::getConnection();
        $intermediateTable = $connection->quoteIdentifier($intermediateTable);
        $objectForeignKey = $connection->quoteIdentifier($objectForeignKey);
        $relatedForeignKey = $connection->quoteIdentifier($relatedForeignKey);
        $id = $connection->quoteIdentifier('id');
        $objectId = $this->quoteValue($connection, $object->id);
        $query = "SELECT $relatedQuotedTable.* FROM $relatedQuotedTable INNER JOIN $intermediateTable
            ON $intermediateTable.$relatedForeignKey = $relatedQuotedTable.$id AND $intermediateTable.$objectForeignKey = $objectId
            ORDER BY $relatedQuotedTable.$id";
        return $relatedClass::findAllBySql($query);
    }

    public function check(Base $object): void
    {
        if (!$this->property->isInitialized($object)) {
            return;
        }
        $class = $this->relatedClass ?: $this->relatedClass();
        if (!is_subclass_of($class, Base::class)) {
            throw new AssociationException('Invalid related model class');
        }
        foreach ($this->property->getValue($object) as $related) {
            if (!$related instanceof $class) {
                throw new AssociationException('Invalid many-to-many item; expected ' . $class);
            }
            if (!$related->isNew) {
                $related->relationId();
            } else {
                $this->checkInverse($object, $related);
            }
        }
    }

    private function mapping(Base $object, string $relatedClass): array
    {
        $ownTable = $object::getTableName();
        $relatedTable = $relatedClass::getTableName();
        return [
            $this->getIntermediateTableName($object, $ownTable, $relatedTable),
            $this->callablePropertyValue($this->objectForeignKey, $object) ?: $ownTable . '_id',
            $this->callablePropertyValue($this->relatedForeignKey, $object) ?: $relatedTable . '_id',
        ];
    }

    private function checkInverse(Base $object, Base $related): void
    {
        [$table, $ownKey, $relatedKey] = $this->mapping($object, $related::class);
        foreach (Association::getAll($related) as $inverse) {
            if (!$inverse instanceof self || !$inverse->property->isInitialized($related)) {
                continue;
            }
            $class = $inverse->relatedClass ?: $inverse->relatedClass();
            if (!$object instanceof $class || $inverse->mapping($related, $class) !== [$table, $relatedKey, $ownKey]) {
                continue;
            }
            foreach ($inverse->property->getValue($related) as $item) {
                if ($item === $object || ($item instanceof Base && !$item->isNew && !$object->isNew
                    && $item::class === $object::class && $item->relationId() === $object->relationId())) {
                    continue 2;
                }
            }
            throw new AssociationException('Conflicting inverse many-to-many collection');
        }
    }

    /**
     * @throws ActiveRecordException
     * @throws ValidationException
     */
    public function save(Base $object): void
    {
        if (!$this->property->isInitialized($object)) {
            return;
        }
        $this->check($object);
        $connection = $object::getConnection();

        $relatedClass = $this->relatedClass ?: $this->getClassFromArrayShape($this->property);
        if (!is_subclass_of($relatedClass, Base::class)) {
            return;
        }
        $objectTable = $object::class::getTableName();
        $relatedTable = $relatedClass::getTableName();
        $intermediateTable = $this->getIntermediateTableName($object, $objectTable, $relatedTable);
        $objectForeignKey = $this->callablePropertyValue($this->objectForeignKey, $object) ?: ($objectTable . '_id');
        $relatedForeignKey = $this->callablePropertyValue($this->relatedForeignKey, $object) ?: ($relatedTable . '_id');
        $intermediateTable = $connection->quoteIdentifier($intermediateTable);
        $objectForeignKey = $connection->quoteIdentifier($objectForeignKey);
        $relatedForeignKey = $connection->quoteIdentifier($relatedForeignKey);
        $objectId = $this->quoteValue($connection, $object->id);


        $newIds = [];
        foreach ($this->property->getValue($object) as $relatedObject) {
            if (!$relatedObject instanceof Base) {
                continue;
            }
            if ($relatedObject->isNew) {
                $relatedObject->save();
            }
            $newIds[] = $relatedObject->relationId();
        }
        $newIds = array_values(array_unique($newIds));
        $existingIds = $connection->query("SELECT $relatedForeignKey FROM $intermediateTable WHERE $objectForeignKey = $objectId")->fetchAll(PDO::FETCH_COLUMN);
        $insertValues = implode(',', array_map(fn($relatedObjectId) => "($objectId," . $this->quoteValue($connection, $relatedObjectId) . ")", array_diff($newIds, $existingIds)));
        $removeValues = implode(',', array_map(fn($id) => $this->quoteValue($connection, $id), array_diff($existingIds, $newIds)));

        if ($insertValues) {
            $connection->exec("INSERT INTO $intermediateTable ($objectForeignKey, $relatedForeignKey) VALUES $insertValues");
        }
        if ($removeValues) {
            $connection->exec("DELETE FROM $intermediateTable WHERE $objectForeignKey = $objectId AND $relatedForeignKey IN ($removeValues)");
        }
    }

    private function quoteValue(Connection $connection, mixed $value): string
    {
        $expression = new Value($value);
        $expression->connection = $connection;
        return (string) $expression;
    }

    private function getIntermediateTableName(Base $object, string $objectTable, string $relatedTable): string
    {
        if (empty($this->intermediateTable)) {
            return strcmp($objectTable, $relatedTable) < 0 ? $objectTable . '_' . $relatedTable : $relatedTable . '_' . $objectTable;
        }
        return $this->callablePropertyValue($this->intermediateTable, $object);
    }
}
