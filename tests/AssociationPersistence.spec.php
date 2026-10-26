<?php

use Fixtures\{RelationOwner, RelationChild};
use Gforces\ActiveRecord\Connection;
use Gforces\ActiveRecord\Association;
use Kahlan\Plugin\Double;

describe('Association persistence', function () {
    beforeEach(function () {
        $this->queries = $queries = new ArrayObject();
        $this->connection = Double::instance(['extends' => Connection::class, 'methods' => ['__construct'], 'layer' => true]);
        allow($this->connection)->toReceive('quote')->andRun(fn($value) => "'$value'");
        allow($this->connection)->toReceive('quoteIdentifier')->andRun(fn($value) => "`$value`");
        allow($this->connection)->toReceive('exec')->andRun(function ($sql) use ($queries) { $queries[] = $sql; return 1; });
        allow($this->connection)->toReceive('lastInsertId')->andRun(fn() => (string) count($queries));
        RelationOwner::setConnection($this->connection);
        RelationChild::setConnection($this->connection);
    });

    it('saves a new BelongsTo target before inserting the child', function () {
        $child = new RelationChild();
        $child->owner = new RelationOwner();
        $child->save();
        expect($this->queries->getArrayCopy())->toBe([
            "INSERT INTO `fixtures_relation_owner` (`name`) VALUES ('owner')",
            "INSERT INTO `fixtures_relation_child` (`owner_id`,`name`) VALUES (1,'child')",
        ]);
        expect($child->ownerId())->toBe(1);
    });

    it('saves children after the owner and sets private foreign keys', function () {
        $owner = new RelationOwner();
        $owner->children = ['first' => new RelationChild(), 'second' => new RelationChild()];
        $owner->save();
        expect(count($this->queries))->toBe(4);
        expect($this->queries[0])->toBe("INSERT INTO `fixtures_relation_owner` (`name`) VALUES ('owner')");
        expect($owner->children['first']->ownerId())->toBe($owner->id);
        expect($owner->children['second']->ownerId())->toBe($owner->id);
        expect($owner->children['second']->isNew)->toBe(false);
    });

    it('handles initialized inverse references without saving recursively', function () {
        $owner = new RelationOwner();
        $child = new RelationChild();
        $owner->children = [$child];
        $child->owner = $owner;
        $owner->save();
        expect(count($this->queries))->toBe(3);
        expect($child->ownerId())->toBe($owner->id);
    });

    it('handles saving from the child side of a bidirectional graph', function () {
        $owner = new RelationOwner();
        $child = new RelationChild();
        $owner->children = [$child];
        $child->owner = $owner;
        $child->save();
        expect(count($this->queries))->toBe(4);
        expect($child->ownerId())->toBe($owner->id);
    });

    it('persists explicit null BelongsTo without creating a target', function () {
        $child = new RelationChild();
        $child->owner = null;
        $child->save();
        expect($this->queries->getArrayCopy())->toBe(["INSERT INTO `fixtures_relation_child` (`owner_id`,`name`) VALUES (NULL,'child')"]);
    });

    it('synchronizes empty HasMany collections by detaching nullable children', function () {
        $owner = new RelationOwner();
        $owner->save();
        $this->queries->exchangeArray([]);
        $owner->children = [];
        $owner->save();
        expect($this->queries->getArrayCopy())->toBe([
            "UPDATE `fixtures_relation_owner` SET `id` = 1, `name` = 'owner' WHERE `id` = 1",
            'UPDATE `fixtures_relation_child` SET `owner_id` = NULL WHERE `owner_id` = 1 AND 1 = 1',
        ]);
    });

    it('updates only foreign keys of existing associated models', function () {
        $owner = new RelationOwner();
        $child = new RelationChild();
        $owner->children = [$child];
        $owner->save();
        $this->queries->exchangeArray([]);
        $child->name = 'updated';
        $owner->save();
        expect($this->queries[1])->toBe("UPDATE `fixtures_relation_child` SET `owner_id` = 1 WHERE `id` = 2");
    });

    it('returns null for missing nullable HasOne on new and persisted objects', function () {
        $owner = new RelationOwner();
        $relation = Association::get(RelationOwner::class, 'optionalChild');
        expect(RelationChild::class)->not->toReceive('::__construct');
        expect($relation->load($owner))->toBe(null);
        $owner->id = 1;
        $owner->isNew = false;
        allow(RelationChild::class)->toReceive('::findFirstByAttribute')->andReturn(null);
        expect($relation->load($owner))->toBe(null);
    });

    it('rejects invalid HasMany items and releases the save guard for retries', function () {
        $owner = new RelationOwner();
        $owner->children = ['invalid'];
        expect(fn() => $owner->save())->toThrow(new \Gforces\ActiveRecord\AssociationException());
        $owner->children = [new RelationChild()];
        $owner->save();
        expect($owner->children[0]->isNew)->toBe(false);
    });
});
