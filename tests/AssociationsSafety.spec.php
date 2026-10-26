<?php

use Fixtures\Simple;
use Gforces\ActiveRecord\Associations\HasMany;
use Gforces\ActiveRecord\Associations\HasAndBelongsToMany;
use Gforces\ActiveRecord\Connection;
use Gforces\ActiveRecord\Connection\Drivers\Mysql;
use Gforces\ActiveRecord\Connection\Drivers\Pgsql;
use Kahlan\Plugin\Double;

describe('Association SQL safety', function () {
    it('routes HasMany identifiers and values through array criteria', function () {
        $owner = new Simple();
        $owner->id = 5;
        $owner->isNew = false;
        $relation = new HasMany(Simple::class, 'owner`key');
        allow(Simple::class)->toReceive('::findAll')->andReturn([]);
        expect(Simple::class)->toReceive('::findAll')->with(['owner`key' => 5], 'id')->once();
        $relation->load($owner);
    });

    it('quotes all many-to-many identifiers and values for reads and writes', function () {
        foreach ([new Mysql(), new Pgsql()] as $driver) {
            $connection = Double::instance(['extends' => Connection::class, 'methods' => ['__construct'], 'layer' => true]);
            (new ReflectionProperty(Connection::class, 'driver'))->setValue($connection, $driver);
            allow($connection)->toReceive('quote')->andRun(fn($value) => "'" . str_replace("'", "''", $value) . "'");
            Simple::setConnection($connection);
            $q = $driver->getIdentifierQuotingCharacter();
            $table = $connection->quoteIdentifier("link$q");
            $ownerKey = $connection->quoteIdentifier("owner$q");
            $relatedKey = $connection->quoteIdentifier("related$q");
            $relatedTable = Simple::getQuotedTableName();
            $id = $connection->quoteIdentifier('id');
            $owner = new class extends \Gforces\ActiveRecord\Base {
                public string $id = "a' OR 1=1 --";
                public array $links = [];
            };
            $owner->isNew = false;
            $owner::setConnection($connection);
            $relation = new HasAndBelongsToMany(Simple::class, "link$q", "owner$q", "related$q");
            $relation->property = new ReflectionProperty($owner, 'links');
            $ownerId = "'a'' OR 1=1 --'";
            allow(Simple::class)->toReceive('::findAllBySql')->andReturn([]);
            expect(Simple::class)->toReceive('::findAllBySql')->with("SELECT $relatedTable.* FROM $relatedTable INNER JOIN $table
            ON $table.$relatedKey = $relatedTable.$id AND $table.$ownerKey = $ownerId
            ORDER BY $relatedTable.$id")->once();
            $relation->load($owner);

            $statement = Double::instance(['extends' => Connection\Statement::class, 'methods' => ['__construct'], 'layer' => true]);
            allow($statement)->toReceive('fetchAll')->andReturn(["old' OR 1=1 --"]);
            allow($connection)->toReceive('query')->andReturn($statement);
            allow($connection)->toReceive('exec')->andReturn(1);
            expect($connection)->toReceive('query')->with("SELECT $relatedKey FROM $table WHERE $ownerKey = $ownerId")->once();
            $related = (new ReflectionMethod(\Gforces\ActiveRecord\Base::class, 'createObject'))->invoke(
                null, new ReflectionClass(Simple::class), (object) ['id' => 9]
            );
            $owner->links = [$related];
            expect($connection)->toReceive('exec')->with("INSERT INTO $table ($ownerKey, $relatedKey) VALUES ($ownerId,9)")->once();
            expect($connection)->toReceive('exec')->with("DELETE FROM $table WHERE $ownerKey = $ownerId AND $relatedKey IN ('old'' OR 1=1 --')")->once();
            $relation->save($owner);
        }
    });
});
