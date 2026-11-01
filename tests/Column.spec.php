<?php

use Fixtures\Simple;
use Fixtures\WithAutoIncrementProperty;
use Fixtures\WithoutAutoIncrementProperty;
use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Column;

describe(Column::class, function () {
    it('returns all initialized column values for model', function () {
        $simple = new Simple();
        $simple->id = 100;
        expect(Column::getValues($simple))->toBe(['id' => 100, 'enabled' => false]);
        $simple->name = 'Bill';
        expect(Column::getValues($simple))->toBe(['id' => 100, 'name' => 'Bill', 'enabled' => false]);
    });
    it('returns Column definition for property', function () {
        expect(Column::get(Simple::class, 'id'))->toBeAnInstanceOf(Column::class);
    });
    it('throws exception when property is not a Column', function () {
        expect(fn() => Column::get(Simple::class, 'notExisting'))->toThrow(new ActiveRecordException());
        expect(fn() => Column::get(Simple::class, 'noDbColumn'))->toThrow(new ActiveRecordException());
    });
    describe('AutoIncrement property', function () {
        it('resolves callable foreign keys for the supplied object', function () {
            $model = new Fixtures\DynamicSharedKeyDetails();
            expect(Column::getAutoIncrementProperty($model))->toBeNull();
            $model->keyName = 'owner_id';
            expect(Column::getAutoIncrementProperty($model)->name)->toBe('id');
        });
        it('requires an object when inference reaches a callable foreign key', function () {
            expect(fn() => Column::getAutoIncrementProperty(Fixtures\DynamicSharedKeyDetails::class))
                ->toThrow(new Gforces\ActiveRecord\AssociationException('An object is required to resolve a callable association property'));
        });
        it('does not resolve callable keys when auto-increment is explicitly configured', function () {
            $enabled = new class extends Fixtures\DynamicSharedKeyDetails {
                #[Column(autoIncrement: true)] public int $id;
            };
            $disabled = new class extends Fixtures\DynamicSharedKeyDetails {
                #[Column(autoIncrement: false)] public int $id;
            };
            $other = new class extends Fixtures\DynamicSharedKeyDetails {
                #[Column(autoIncrement: true)] public int $sequence;
            };
            expect(Column::getAutoIncrementProperty($enabled::class)->name)->toBe('id');
            expect(Column::getAutoIncrementProperty($disabled::class))->toBeNull();
            expect(Column::getAutoIncrementProperty($other::class)->name)->toBe('sequence');
        });
        it('does not infer auto-increment for a declared shared foreign key', function () {
            expect(Column::getAutoIncrementProperty(Fixtures\SharedKeyDetails::class))->toBeNull();
            expect(Column::getAutoIncrementProperty(Fixtures\SharedKeyOwner::class)->name)->toBe('id');
            expect(Column::getAutoIncrementProperty(Fixtures\RelationChild::class)->name)->toBe('id');
        });
        it('honors explicit auto-increment on a shared foreign key', function () {
            $model = new class extends Fixtures\SharedKeyDetails {
                #[Column(autoIncrement: true)] public int $id;
            };
            expect(Column::getAutoIncrementProperty($model::class)->name)->toBe('id');
        });
        it('honors another explicit auto-increment column with an inherited shared key', function () {
            $model = new class extends Fixtures\SharedKeyDetails {
                #[Column(autoIncrement: true)] public int $sequence;
            };
            expect(Column::getAutoIncrementProperty($model::class)->name)->toBe('sequence');
        });
        it('returns first property marked as AutoIncrement', function() {
            expect(Column::getAutoIncrementProperty(WithAutoIncrementProperty::class)->name)->toBe('index');
            expect(Column::get(WithAutoIncrementProperty::class, 'index')->autoIncrement)->toBe(true);
        });
        it('returns id column if exists and no other is marked as AutoIncrement', function() {
            expect(Column::getAutoIncrementProperty(Simple::class)->name)->toBe('id');
            expect(Column::get(Simple::class, 'id')->autoIncrement)->toBeNull();
        });
        it('returns null if id has turned autoIncrement off and any other is marked', function() {
            expect(Column::getAutoIncrementProperty(WithoutAutoIncrementProperty::class))->toBeNull();
        });
    });
});
