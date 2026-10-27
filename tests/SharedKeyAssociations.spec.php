<?php

use Fixtures\{SharedKeyOwner, SharedKeyDetails};
use Gforces\ActiveRecord\{Base, Connection};
use Kahlan\Plugin\Double;

describe('Association cleanup with a shared primary and foreign key', function () {
    beforeEach(function () {
        $this->sql = $sql = new ArrayObject();
        $connection = Double::instance(['extends' => Connection::class, 'methods' => ['__construct'], 'layer' => true]);
        allow($connection)->toReceive('quoteIdentifier')->andRun(fn($value) => "`$value`");
        allow($connection)->toReceive('exec')->andRun(function ($query) use ($sql) {
            $sql[] = $query;
            return 1;
        });
        SharedKeyOwner::setConnection($connection);
        SharedKeyDetails::setConnection($connection);
        $this->hydrate = fn($class, $id) => (new ReflectionMethod(Base::class, 'createObject'))
            ->invoke(null, new ReflectionClass($class), (object) ['id' => $id]);
        $this->deletes = fn() => array_values(array_filter($sql->getArrayCopy(), fn($query) => str_starts_with($query, 'DELETE')));
    });

    it('keeps both owner and retained-ID constraints when saving HasOne for successive owners', function () {
        foreach ([1, 2] as $id) {
            $owner = ($this->hydrate)(SharedKeyOwner::class, $id);
            $owner->details = ($this->hydrate)(SharedKeyDetails::class, $id);
            $owner->save();
        }
        // Each predicate is false for every row: neither owner's details may be deleted.
        expect(($this->deletes)())->toBe([
            'DELETE FROM `fixtures_shared_key_details` WHERE `id` = 1 AND `id` NOT IN (1)',
            'DELETE FROM `fixtures_shared_key_details` WHERE `id` = 2 AND `id` NOT IN (2)',
        ]);
    });

    it('limits clearing nullable HasOne to the current owner', function () {
        $owner = ($this->hydrate)(SharedKeyOwner::class, 1);
        $owner->optionalDetails = null;
        $owner->save();
        expect(($this->deletes)())->toBe([
            'DELETE FROM `fixtures_shared_key_details` WHERE `id` = 1 AND 1 = 1',
        ]);
    });

    it('keeps both constraints when saving HasMany with an id foreign key', function () {
        $owner = ($this->hydrate)(SharedKeyOwner::class, 1);
        $owner->children = [($this->hydrate)(SharedKeyDetails::class, 1)];
        $owner->save();
        expect(($this->deletes)())->toBe([
            'DELETE FROM `fixtures_shared_key_details` WHERE `id` = 1 AND `id` NOT IN (1)',
        ]);
    });

    it('limits clearing HasMany to the current owner even with no retained IDs', function () {
        $owner = ($this->hydrate)(SharedKeyOwner::class, 1);
        $owner->children = [];
        $owner->save();
        expect(($this->deletes)())->toBe([
            'DELETE FROM `fixtures_shared_key_details` WHERE `id` = 1 AND 1 = 1',
        ]);
    });
});
