<?php


namespace Gforces\ActiveRecord\Associations;

use Attribute;
use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Association;
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\ValidationException;
use PDO;

#[Attribute(Attribute::TARGET_PROPERTY)]
class HasAndBelongsToMany extends Association
{
    public function __construct(private readonly string $relatedClass = '', private readonly array|string $intermediateTable = '', private readonly string $objectForeignKey = '', private readonly string $relatedForeignKey = '')
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
        $objectForeignKey = $this->objectForeignKey ?: $objectTable . '_id';
        $relatedForeignKey = $this->relatedForeignKey ?: $relatedTable . '_id';

        $query = "SELECT `$relatedTable`.* FROM `$relatedTable` INNER JOIN `$intermediateTable` 
            ON `$intermediateTable`.`$relatedForeignKey` = `$relatedTable`.`id` AND `$intermediateTable`.`$objectForeignKey`= $object->id
            ORDER BY `$relatedTable`.id";
        return $relatedClass::findAllBySql($query);
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
        $connection = $object::getConnection();

        $relatedClass = $this->relatedClass ?: $this->getClassFromArrayShape($this->property);
        if (!is_subclass_of($relatedClass, Base::class)) {
            return;
        }
        $objectTable = $object::class::getTableName();
        $relatedTable = $relatedClass::getTableName();
        $intermediateTable = $this->getIntermediateTableName($object, $objectTable, $relatedTable);
        $objectForeignKey = $this->objectForeignKey ?: $objectTable . '_id';
        $relatedForeignKey = $this->relatedForeignKey ?: $relatedTable . '_id';
        $objectId = $object->id;

        $existingIds = $connection->query("SELECT `$relatedForeignKey` FROM `$intermediateTable` WHERE `$objectForeignKey` = $objectId")->fetchAll(PDO::FETCH_COLUMN);
        $newIds = [];
        foreach ($this->property->getValue($object) as $relatedObject) {
            if (!$relatedObject instanceof Base) {
                continue;
            }
            if ($relatedObject->isNew) {
                $relatedObject->save();
            }
            $newIds[] = $relatedObject->id;
        }
        $insertValues = implode(',', array_map(fn($relatedObjectId) => "($objectId,$relatedObjectId)", array_diff($newIds, $existingIds)));
        $removeValues = implode(',', array_diff($existingIds, $newIds));

        if ($insertValues) {
            $connection->exec("INSERT INTO $intermediateTable ($objectForeignKey, $relatedForeignKey) VALUES $insertValues");
        }
        if ($removeValues) {
            $connection->exec("DELETE FROM $intermediateTable WHERE $objectForeignKey = $objectId AND $relatedForeignKey IN ($removeValues)");
        }
    }

    private function getIntermediateTableName(Base $object, string $objectTable, string $relatedTable): string
    {
        if (empty($this->intermediateTable)) {
            return strcmp($objectTable, $relatedTable) < 0 ? $objectTable . '_' . $relatedTable : $relatedTable . '_' . $objectTable;
        }
        if (is_callable($this->intermediateTable)) {
            return call_user_func($this->intermediateTable, $object);
        }
        return $this->intermediateTable;
    }
}
