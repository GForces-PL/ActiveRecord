<?php

use Fixtures\Tracked;
use Fixtures\MutableString;
use Gforces\ActiveRecord\Connection;
use Kahlan\Plugin\Double;

describe('Change tracking snapshots', function () {
    beforeEach(function () {
        $this->connection = Double::instance(['extends' => Connection::class, 'methods' => ['__construct'], 'layer' => true]);
        allow($this->connection)->toReceive('quote')->andRun(fn($value) => "'$value'");
        allow($this->connection)->toReceive('quoteIdentifier')->andRun(fn($value) => "`$value`");
        allow($this->connection)->toReceive('exec')->andReturn(1);
        allow($this->connection)->toReceive('lastInsertId')->andReturn('42');
        Tracked::setConnection($this->connection);
        $statement = Double::instance(['extends' => Connection\Statement::class, 'methods' => ['__construct'], 'layer' => true]);
        allow($statement)->toReceive('fetchObject')->andReturn((object) [
            'id' => 1, 'date' => '2024-01-01 00:00:00', 'label' => 'old', 'data' => '{"nested":{"value":1}}',
        ], false);
        allow($this->connection)->toReceive('query')->andReturn($statement);
    });

    it('inserts initialized defaults and snapshots the generated id', function () {
        $model = new Tracked();
        expect($this->connection)->toReceive('exec')->with("INSERT INTO `fixtures_tracked` (`name`,`enabled`,`optional`) VALUES ('default',0,NULL)")->once();
        $model->save(false);
        expect($model->originals()['id'])->toBe(42);
        expect($model->changed('id'))->toBe(false);
        expect($this->connection)->toReceive('exec')->once();
        $model->save(false);
    });

    it('detects in-place date mutations and refreshes the snapshot after saving', function () {
        $model = Tracked::findAllBySql('SELECT test')[0];
        $model->date->modify('+1 day');
        expect($model->changed('date'))->toBe(true);
        expect($model->originals()['date']->format('Y-m-d'))->toBe('2024-01-01');
        expect($this->connection)->toReceive('exec')->with("UPDATE `fixtures_tracked` SET `date` = '2024-01-02 00:00:00' WHERE `id` = 1")->once();
        $model->save(false);
        expect($model->changed('date'))->toBe(false);
        expect($model->originals()['date']->format('Y-m-d'))->toBe('2024-01-02');
        $model->save(false);
        expect($this->connection)->toReceive('exec')->once();
    });

    it('compares replacement objects by persisted value', function () {
        $model = Tracked::findAllBySql('SELECT test')[0];
        $model->date = new DateTime('2024-01-01 00:00:00');
        $model->label = new MutableString('old');
        expect($model->changed('date'))->toBe(false);
        expect($model->changed('label'))->toBe(false);
        expect($this->connection)->not->toReceive('exec');
        $model->save(false);
    });

    it('detects mutable string values and nested JSON object changes', function () {
        $model = Tracked::findAllBySql('SELECT test')[0];
        $model->label->value = 'new';
        $model->data['nested']['value'] = 2;
        expect($model->changed('label'))->toBe(true);
        expect($model->changed('data'))->toBe(true);
        expect($model->originals()['label']->value)->toBe('old');
        expect($model->originals()['data']['nested']['value'])->toBe(1);
        expect($this->connection)->toReceive('exec')->with('UPDATE `fixtures_tracked` SET `data` = \'{"nested":{"value":2}}\', `label` = \'new\' WHERE `id` = 1')->once();
        $model->save(false);
        expect($model->changed('label'))->toBe(false);
        expect($model->changed('data'))->toBe(false);
    });

    it('does not let callers mutate the internal original values', function () {
        $model = Tracked::findAllBySql('SELECT test')[0];
        $originals = $model->originals();
        $originals['date']->modify('+1 day');
        $originals['label']->value = 'changed';
        $originals['data']['nested']['value'] = 99;
        expect($model->changed('date'))->toBe(false);
        expect($model->changed('label'))->toBe(false);
        expect($model->changed('data'))->toBe(false);
        expect($model->originals()['data']['nested']['value'])->toBe(1);
    });

    it('keeps the old snapshot if a database write throws', function () {
        $model = Tracked::findAllBySql('SELECT test')[0];
        $model->date->modify('+1 day');
        allow($this->connection)->toReceive('exec')->andRun(fn() => throw new PDOException('write failed'));
        expect(fn() => $model->save(false))->toThrow(new PDOException());
        expect($model->changed('date'))->toBe(true);
        expect($model->originals()['date']->format('Y-m-d'))->toBe('2024-01-01');
    });
});
