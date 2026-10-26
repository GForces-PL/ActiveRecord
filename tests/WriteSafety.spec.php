<?php

use Fixtures\CompositeKey;
use Fixtures\Simple;
use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Column;
use Gforces\ActiveRecord\Connection;
use Gforces\ActiveRecord\PrimaryKey;
use Kahlan\Plugin\Double;

describe('Write safety', function () {
    beforeEach(function () {
        $this->connection = Double::instance(['extends' => Connection::class, 'methods' => ['__construct'], 'layer' => true]);
        allow($this->connection)->toReceive('quote')->andRun(fn($value) => "'$value'");
        allow($this->connection)->toReceive('quoteIdentifier')->andRun(fn($value) => "`$value`");
        allow($this->connection)->toReceive('exec')->andReturn(1);
        CompositeKey::setConnection($this->connection);
        Simple::setConnection($this->connection);
        $this->load = function (string $class, array $row) {
            $statement = Double::instance(['extends' => Connection\Statement::class, 'methods' => ['__construct'], 'layer' => true]);
            allow($statement)->toReceive('fetchObject')->andReturn((object) $row, false);
            allow($this->connection)->toReceive('query')->andReturn($statement);
            return $class::findAllBySql('SELECT test')[0];
        };
    });

    it('requires every explicit key component and never falls back to id', function () {
        $model = new CompositeKey();
        $model->id = 99;
        expect(fn() => PrimaryKey::getValues($model))->toThrow(new ActiveRecordException());
        $model->tenant = 1;
        expect(fn() => PrimaryKey::getValues($model))->toThrow(new ActiveRecordException());
        $model->number = null;
        expect(fn() => PrimaryKey::getValues($model))->toThrow(new ActiveRecordException());
        $model->number = 0;
        expect(PrimaryKey::getValues($model))->toBe(['tenant' => 1, 'number' => 0]);
    });

    it('rejects writes on partially loaded composite keys even if completed later', function () {
        $model = ($this->load)(CompositeKey::class, ['tenant' => 1, 'name' => 'old']);
        expect($this->connection)->not->toReceive('exec');
        expect(fn() => $model->save(false))->toThrow(new ActiveRecordException());
        expect(fn() => $model->remove())->toThrow(new ActiveRecordException());
        $model->number = 2;
        expect(fn() => $model->save(false))->toThrow(new ActiveRecordException());
        expect(fn() => $model->remove())->toThrow(new ActiveRecordException());
    });

    it('does not treat PHP defaults as keys returned by the database', function () {
        $prototype = new class extends Simple {
            #[Column] public int $id = 100;
        };
        $prototype::setConnection($this->connection);
        $model = ($this->load)($prototype::class, ['name' => 'partial']);
        expect($this->connection)->not->toReceive('exec');
        expect(fn() => $model->save(false))->toThrow(new ActiveRecordException());
        expect(fn() => $model->remove())->toThrow(new ActiveRecordException());
    });

    it('rejects null key components in loaded records', function () {
        $model = ($this->load)(CompositeKey::class, ['tenant' => 1, 'number' => null]);
        expect($this->connection)->not->toReceive('exec');
        expect(fn() => $model->save(false))->toThrow(new ActiveRecordException());
        expect(fn() => $model->remove())->toThrow(new ActiveRecordException());
    });

    it('uses all original key components for normal writes', function () {
        $model = ($this->load)(CompositeKey::class, ['tenant' => 1, 'number' => 2, 'name' => 'old']);
        $model->name = 'new';
        expect($this->connection)->toReceive('exec')->with("UPDATE `fixtures_composite_key` SET `tenant` = 1, `number` = 2, `name` = 'new' WHERE `tenant` = 1 AND `number` = 2")->once();
        $model->save(false);
        expect($this->connection)->toReceive('exec')->with('DELETE FROM `fixtures_composite_key` WHERE `tenant` = 1 AND `number` = 2')->once();
        $model->remove();
    });

    it('rejects changed composite keys before executing any write', function () {
        $model = ($this->load)(CompositeKey::class, ['tenant' => 1, 'number' => 2]);
        $model->number = 3;
        expect($this->connection)->not->toReceive('exec');
        expect(fn() => $model->save(false))->toThrow(new ActiveRecordException());
        expect(fn() => $model->remove())->toThrow(new ActiveRecordException());
    });

    it('rejects changed default ids and missing default ids', function () {
        $model = ($this->load)(Simple::class, ['id' => 1]);
        $model->id = 2;
        expect($this->connection)->not->toReceive('exec');
        expect(fn() => $model->save(false))->toThrow(new ActiveRecordException());
        expect(fn() => $model->remove())->toThrow(new ActiveRecordException());
        unset($model->id);
        expect(fn() => $model->remove())->toThrow(new ActiveRecordException());
    });

    it('protects keys with change tracking enabled as well', function () {
        $prototype = new class extends Simple {
            protected static bool $keepAttributeChanges = true;
        };
        $prototype::setConnection($this->connection);
        $model = ($this->load)($prototype::class, ['id' => 1, 'name' => 'old']);
        expect($this->connection)->not->toReceive('exec');
        $model->save(false);
        $model->id = 2;
        expect(fn() => $model->save(false))->toThrow(new ActiveRecordException());
        expect(fn() => $model->remove())->toThrow(new ActiveRecordException());
    });

    it('remembers generated ids after insertion', function () {
        allow($this->connection)->toReceive('lastInsertId')->andReturn('42');
        $model = new Simple();
        $model->name = 'new';
        $model->save(false);
        expect($model->id)->toBe(42);
        expect($this->connection)->toReceive('exec')->with('DELETE FROM `fixtures_simple` WHERE `id` = 42')->once();
        $model->remove();
    });

    it('retains zero conditions and pagination', function () {
        expect(Simple::buildQuery('0', limit: 0, offset: 0))->toBe('SELECT * FROM `fixtures_simple` WHERE 0 LIMIT 0 OFFSET 0');
        expect(Simple::buildQuery())->toBe('SELECT * FROM `fixtures_simple`');
        expect($this->connection)->toReceive('exec')->with('DELETE FROM `fixtures_simple` WHERE 0')->once();
        Simple::deleteAll('0');
        expect($this->connection)->toReceive('exec')->with('UPDATE `fixtures_simple` SET `enabled` = 0 WHERE 0')->once();
        Simple::updateAll(['enabled' => false], '0');
    });
});
