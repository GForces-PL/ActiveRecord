<?php

use Fixtures\{SharedKeyOwner, SharedKeyDetails, RelationOwner, RelationStrictChild};
use Gforces\ActiveRecord\{Base, Connection};
use Kahlan\Plugin\Double;

describe('HasOne sharing the primary key (foreignKey: id)', function () {
    beforeEach(function () {
        $this->sql = $sql = new ArrayObject();
        $this->connection = Double::instance(['extends' => Connection::class, 'methods' => ['__construct'], 'layer' => true]);
        allow($this->connection)->toReceive('quote')->andRun(fn($value) => "'$value'");
        allow($this->connection)->toReceive('quoteIdentifier')->andRun(fn($value) => "`$value`");
        allow($this->connection)->toReceive('exec')->andRun(function ($query) use ($sql) { $sql[] = $query; return 1; });
        $sequence = (object) ['id' => 100];
        allow($this->connection)->toReceive('lastInsertId')->andRun(fn() => (string) ++$sequence->id);
        foreach ([SharedKeyOwner::class, SharedKeyDetails::class, RelationOwner::class, RelationStrictChild::class] as $class) {
            $class::setConnection($this->connection);
        }
        $this->hydrate = fn($class, $row) => (new ReflectionMethod(Base::class, 'createObject'))->invoke(null, new ReflectionClass($class), (object) $row);
        $this->deletes = fn() => array_values(array_filter($this->sql->getArrayCopy(), fn($query) => str_starts_with($query, 'DELETE')));
    });

    it('never deletes other owners\' rows when the related record is present', function () {
        $owner = ($this->hydrate)(SharedKeyOwner::class, ['id' => 1]);
        $owner->details = ($this->hydrate)(SharedKeyDetails::class, ['id' => 1]);
        $owner->name = 'changed';
        $owner->save();
        expect(($this->deletes)())->toBe([]);
        foreach ($this->sql as $query) {
            expect($query)->not->toContain('NOT IN');
        }
    });

    it('never deletes other owners\' rows for a new related record', function () {
        $owner = ($this->hydrate)(SharedKeyOwner::class, ['id' => 1]);
        $owner->details = new SharedKeyDetails();
        $owner->save();
        expect(($this->deletes)())->toBe([]);
        expect($this->sql->getArrayCopy())->toContain("INSERT INTO `fixtures_shared_key_details` (`id`,`note`) VALUES (1,'details')");
    });

    it('deletes only the owner\'s own row when the related record is null', function () {
        $owner = ($this->hydrate)(SharedKeyOwner::class, ['id' => 1]);
        $owner->details = null;
        $owner->save();
        expect(($this->deletes)())->toBe(['DELETE FROM `fixtures_shared_key_details` WHERE `id` = 1']);
    });

    it('keeps the owner condition in HasMany removal', function () {
        $owner = ($this->hydrate)(RelationOwner::class, ['id' => 1]);
        $owner->strictChildren = [($this->hydrate)(RelationStrictChild::class, ['id' => 8, 'owner_id' => 1])];
        $owner->save();
        expect(($this->deletes)())->toBe(['DELETE FROM `fixtures_relation_strict_child` WHERE `owner_id` = 1 AND `id` NOT IN (8)']);
    });
});
