<?php

use Fixtures\{RelationOwner, RelationChild, RelationStrictChild, RelationTag};
use Gforces\ActiveRecord\{Base, Column, Connection, AssociationException};
use Gforces\ActiveRecord\Associations\BelongsTo;
use Kahlan\Plugin\Double;

describe('Association synchronization contracts', function () {
    beforeEach(function () {
        $this->sql = $sql = new ArrayObject();
        $this->connection = Double::instance(['extends' => Connection::class, 'methods' => ['__construct'], 'layer' => true]);
        allow($this->connection)->toReceive('quote')->andRun(fn($value) => "'$value'");
        allow($this->connection)->toReceive('quoteIdentifier')->andRun(fn($value) => "`$value`");
        allow($this->connection)->toReceive('exec')->andRun(function ($query) use ($sql) { $sql[] = $query; return 1; });
        $sequence = (object) ['id' => 100];
        allow($this->connection)->toReceive('lastInsertId')->andRun(fn() => (string) ++$sequence->id);
        foreach ([RelationOwner::class, RelationChild::class, RelationStrictChild::class, RelationTag::class] as $class) {
            $class::setConnection($this->connection);
        }
        $this->hydrate = fn($class, $row) => (new ReflectionMethod(Base::class, 'createObject'))->invoke(null, new ReflectionClass($class), (object) $row);
    });

    it('does not save dirty fields or collections of an existing BelongsTo target', function () {
        $owner = ($this->hydrate)(RelationOwner::class, ['id' => 1]);
        $owner->name = 'dirty';
        $owner->children = [];
        $child = new RelationChild();
        $child->owner = $owner;
        $child->save();
        expect($this->sql->getArrayCopy())->toBe(["INSERT INTO `fixtures_relation_child` (`owner_id`,`name`) VALUES (1,'child')"]);
    });

    it('uses the changed relation when the FK is unchanged, without tracking enabled', function () {
        $child = ($this->hydrate)(RelationChild::class, ['id' => 5, 'owner_id' => 1]);
        $child->owner = ($this->hydrate)(RelationOwner::class, ['id' => 2]);
        $child->save();
        expect($child->ownerId())->toBe(2);
        expect(count($this->sql))->toBe(1);
    });

    it('preserves a changed FK and invalidates an unchanged loaded relation', function () {
        $child = ($this->hydrate)(RelationChild::class, ['id' => 5, 'owner_id' => 1]);
        $first = ($this->hydrate)(RelationOwner::class, ['id' => 1]);
        allow(RelationOwner::class)->toReceive('::find')->with(1)->andReturn($first);
        expect($child->owner)->toBe($first);
        $child->setOwnerId(2);
        $child->save();
        expect($child->ownerId())->toBe(2);
        expect((new ReflectionProperty($child, 'owner'))->isInitialized($child))->toBe(false);
        $second = ($this->hydrate)(RelationOwner::class, ['id' => 2]);
        allow(RelationOwner::class)->toReceive('::find')->with(2)->andReturn($second);
        expect($child->owner)->toBe($second);
    });

    it('rejects inconsistent changes before any writes and allows a corrected retry', function () {
        $child = ($this->hydrate)(RelationChild::class, ['id' => 5, 'owner_id' => 1]);
        $child->owner = ($this->hydrate)(RelationOwner::class, ['id' => 2]);
        $child->setOwnerId(3);
        expect(fn() => $child->save())->toThrow(new AssociationException());
        expect(count($this->sql))->toBe(0);
        $child->setOwnerId(2);
        $child->save();
        expect($child->ownerId())->toBe(2);
    });

    it('checks conflicts in new descendants before writing the parent', function () {
        $owner = new RelationOwner();
        $child = new RelationChild();
        $child->owner = new RelationOwner();
        $child->setOwnerId(9);
        $owner->children = [$child];
        expect(fn() => $owner->save())->toThrow(new AssociationException());
        expect(count($this->sql))->toBe(0);
    });

    it('detaches only omitted nullable children still owned by this parent', function () {
        $owner = ($this->hydrate)(RelationOwner::class, ['id' => 1]);
        $child = ($this->hydrate)(RelationChild::class, ['id' => 7, 'owner_id' => 2]);
        $child->name = 'unsaved';
        $owner->children = [$child];
        $owner->save();
        expect($child->ownerId())->toBe(1);
        expect($child->name)->toBe('unsaved');
        expect($this->sql[1])->toBe('UPDATE `fixtures_relation_child` SET `owner_id` = 1 WHERE `id` = 7');
        expect($this->sql[2])->toBe('UPDATE `fixtures_relation_child` SET `owner_id` = NULL WHERE `owner_id` = 1 AND `id` NOT IN (7)');
    });

    it('deletes omitted non-nullable children including an empty target collection', function () {
        $owner = ($this->hydrate)(RelationOwner::class, ['id' => 1]);
        $child = ($this->hydrate)(RelationStrictChild::class, ['id' => 8, 'owner_id' => 2]);
        $owner->strictChildren = [$child];
        $owner->save();
        expect($this->sql[2])->toBe('DELETE FROM `fixtures_relation_strict_child` WHERE `owner_id` = 1 AND `id` NOT IN (8)');
        $this->sql->exchangeArray([]);
        $owner->strictChildren = [];
        $owner->save();
        expect($this->sql[1])->toBe('DELETE FROM `fixtures_relation_strict_child` WHERE `owner_id` = 1 AND 1 = 1');
    });

    it('updates only the HasOne FK and detaches the previous nullable child', function () {
        $owner = ($this->hydrate)(RelationOwner::class, ['id' => 1]);
        $child = ($this->hydrate)(RelationChild::class, ['id' => 7, 'owner_id' => 2]);
        $child->name = 'unsaved';
        $owner->optionalChild = $child;
        $owner->save();
        expect($this->sql[1])->toBe('UPDATE `fixtures_relation_child` SET `owner_id` = 1 WHERE `id` = 7');
        expect($this->sql[2])->toBe('UPDATE `fixtures_relation_child` SET `owner_id` = NULL WHERE `owner_id` = 1 AND `id` NOT IN (7)');
        $this->sql->exchangeArray([]);
        $owner->optionalChild = null;
        $owner->save();
        expect($this->sql[1])->toBe('UPDATE `fixtures_relation_child` SET `owner_id` = NULL WHERE `owner_id` = 1 AND 1 = 1');
    });

    it('rejects unsaved dependency cycles instead of silently skipping inserts', function () {
        $a = new class extends Base {
            #[Column] public int $id;
            #[Column] public int $parent_id;
            #[BelongsTo] public Base $parent;
        };
        $b = clone $a;
        $a->parent = $b;
        $b->parent = $a;
        $a::setConnection($this->connection);
        expect(fn() => $a->save())->toThrow(new AssociationException());
        expect(count($this->sql))->toBe(0);
    });

    it('synchronizes inverse many-to-many lists after both new rows exist without duplicate links', function () {
        $links = new ArrayObject();
        $sql = $this->sql;
        allow($this->connection)->toReceive('exec')->andRun(function ($query) use ($links, $sql) {
            $sql[] = $query;
            if (str_starts_with($query, 'INSERT INTO `tag_links`')) {
                if (count($links)) { throw new RuntimeException('duplicate link'); }
                $links[] = [101, 102];
            }
            return 1;
        });
        allow($this->connection)->toReceive('query')->andRun(function ($query) use ($links) {
            $statement = Double::instance(['extends' => Connection\Statement::class, 'methods' => ['__construct'], 'layer' => true]);
            $ids = count($links) ? (str_contains($query, 'SELECT `source_id`') ? [101] : [102]) : [];
            allow($statement)->toReceive('fetchAll')->andReturn($ids);
            return $statement;
        });
        $source = new RelationTag();
        $target = new RelationTag();
        $source->targets = [$target, $target];
        $target->sources = [$source];
        $source->save();
        expect(count($links))->toBe(1);
        expect(count($this->sql))->toBe(3);
        expect($source->id)->toBe(101);
        expect($target->id)->toBe(102);
    });
    it('loads existing children before appending with array syntax', function () {
        $owner = ($this->hydrate)(RelationOwner::class, ['id' => 1]);
        $existing = ($this->hydrate)(RelationChild::class, ['id' => 7, 'owner_id' => 1]);
        allow(RelationChild::class)->toReceive('::findAll')->andReturn([$existing]);
        $new = new RelationChild();
        $owner->children[] = $new;
        expect($owner->children)->toBe([$existing, $new]);
        $owner->save();
        expect($new->isNew)->toBe(false);
        expect($this->sql[3])->toBe('UPDATE `fixtures_relation_child` SET `owner_id` = NULL WHERE `owner_id` = 1 AND `id` NOT IN (7, 101)');
    });

    it('updates an existing FK snapshot without clearing unrelated dirty values', function () {
        $prototype = new class extends Base {
            protected static bool $keepAttributeChanges = true;
            #[Column] public int $id;
            #[Column] private int $owner_id;
            #[Column] public string $name;
            public function changed(string $key): bool { return $this->isAttributeChanged($key); }
        };
        $prototype::setConnection($this->connection);
        $child = ($this->hydrate)($prototype::class, ['id' => 7, 'owner_id' => 1, 'name' => 'old']);
        $child->name = 'dirty';
        $child->persistForeignKey('owner_id', 2);
        expect($child->changed('owner_id'))->toBe(false);
        expect($child->changed('name'))->toBe(true);
        expect(str_contains($this->sql[0], "`name`"))->toBe(false);
    });

    it('handles a private BelongsTo and a custom FK name', function () {
        $prototype = new class extends Base {
            #[Column] public int $id;
            #[Column] private ?int $keeper_id;
            #[BelongsTo(foreignKey: 'keeper_id')] private ?RelationOwner $keeper;
            public function assign(RelationOwner $owner): void { $this->keeper = $owner; }
            public function keeperId(): ?int { return $this->keeper_id; }
        };
        $prototype::setConnection($this->connection);
        $prototype->assign(($this->hydrate)(RelationOwner::class, ['id' => 9]));
        $prototype->save();
        expect($prototype->keeperId())->toBe(9);
        expect(count($this->sql))->toBe(1);
    });

    it('rejects inconsistent new inverse collections before any insert', function () {
        $source = new RelationTag();
        $target = new RelationTag();
        $source->targets = [$target];
        $target->sources = [];
        expect(fn() => $source->save())->toThrow(new AssociationException());
        expect(count($this->sql))->toBe(0);
    });

    it('removes only join rows for an empty many-to-many collection', function () {
        $source = ($this->hydrate)(RelationTag::class, ['id' => 1]);
        $source->targets = [];
        $statement = Double::instance(['extends' => Connection\Statement::class, 'methods' => ['__construct'], 'layer' => true]);
        allow($statement)->toReceive('fetchAll')->andReturn([2, 3]);
        allow($this->connection)->toReceive('query')->andReturn($statement);
        $source->save();
        expect($this->sql[1])->toBe('DELETE FROM `tag_links` WHERE `source_id` = 1 AND `target_id` IN (2,3)');
        expect(count($this->sql))->toBe(2);
    });

    it('does not persist dirty existing many-to-many targets', function () {
        $source = ($this->hydrate)(RelationTag::class, ['id' => 1]);
        $target = ($this->hydrate)(RelationTag::class, ['id' => 2]);
        $target->name = 'unsaved';
        $source->targets = [$target];
        $statement = Double::instance(['extends' => Connection\Statement::class, 'methods' => ['__construct'], 'layer' => true]);
        allow($statement)->toReceive('fetchAll')->andReturn([2]);
        allow($this->connection)->toReceive('query')->andReturn($statement);
        $source->save();
        expect(count($this->sql))->toBe(1);
        expect($target->name)->toBe('unsaved');
    });

    it('supports nullable BelongsTo loading and clearing', function () {
        $child = ($this->hydrate)(RelationChild::class, ['id' => 5, 'owner_id' => null]);
        expect(RelationOwner::class)->not->toReceive('::find');
        expect($child->owner)->toBe(null);
        $child = ($this->hydrate)(RelationChild::class, ['id' => 6, 'owner_id' => 1]);
        $child->owner = null;
        $child->save();
        expect($child->ownerId())->toBe(null);
        expect(str_contains($this->sql[0], '`owner_id` = NULL'))->toBe(true);
    });

});
