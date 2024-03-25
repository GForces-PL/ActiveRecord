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
