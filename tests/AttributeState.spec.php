<?php

use Fixtures\PrivateTracked;
use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Connection;
use Kahlan\Plugin\Double;

describe('Attribute state and persistence contracts', function () {
    beforeEach(function () {
        $this->connection = Double::instance(['extends' => Connection::class, 'methods' => ['__construct'], 'layer' => true]);
        allow($this->connection)->toReceive('quote')->andRun(fn($value) => "'$value'");
        allow($this->connection)->toReceive('quoteIdentifier')->andRun(fn($value) => "`$value`");
        allow($this->connection)->toReceive('exec')->andReturn(1);
        $this->load = function (string $class = PrivateTracked::class) {
            $class::setConnection($this->connection);
            $statement = Double::instance(['extends' => Connection\Statement::class, 'methods' => ['__construct'], 'layer' => true]);
            allow($statement)->toReceive('fetchObject')->andReturn((object) ['id' => 1, 'value' => 'old'], false);
            allow($this->connection)->toReceive('query')->andReturn($statement);
            return $class::findAllBySql('SELECT test')[0];
        };
    });

    it('omits unchanged private fields and fields never initialized', function () {
        $model = ($this->load)();
        expect($model->changed('value'))->toBe(false);
        expect($model->changed('fresh'))->toBe(false);
        expect($this->connection)->not->toReceive('exec');
        $model->save(false);
    });

    it('writes newly initialized fields and resets their changed state', function () {
        $model = ($this->load)();
        $model->assign('fresh', 'new');
        expect($model->changed('fresh'))->toBe(true);
        expect($this->connection)->toReceive('exec')->with("UPDATE `fixtures_private_tracked` SET `fresh` = 'new' WHERE `id` = 1")->once();
        $model->save(false);
        expect($model->changed('fresh'))->toBe(false);
        expect($model->originals()['fresh'])->toBe('new');
    });

    it('writes changed private values', function () {
        $model = ($this->load)();
        $model->assign('value', 'new');
        expect($model->changed('value'))->toBe(true);
        expect($this->connection)->toReceive('exec')->with("UPDATE `fixtures_private_tracked` SET `value` = 'new' WHERE `id` = 1")->once();
        $model->save(false);
        expect($model->changed('value'))->toBe(false);
    });

    it('detects unset but issues no SQL when nothing else changed', function () {
        $model = ($this->load)();
        $model->clear('value');
        expect($model->changed('value'))->toBe(true);
        expect($this->connection)->not->toReceive('exec');
        $model->save(false);
        expect($model->changed('value'))->toBe(false);
        expect(array_key_exists('value', $model->originals()))->toBe(false);
    });

    it('omits unset fields while saving other changes and refreshes the snapshot', function () {
        $model = ($this->load)();
        $model->clear('value');
        $model->assign('fresh', 'new');
        expect($model->changed('value'))->toBe(true);
        expect($this->connection)->toReceive('exec')->with("UPDATE `fixtures_private_tracked` SET `fresh` = 'new' WHERE `id` = 1")->once();
        $model->save(false);
        expect($model->changed('value'))->toBe(false);
        expect($model->changed('fresh'))->toBe(false);
        expect($model->originals())->toBe(['id' => 1, 'fresh' => 'new']);
    });

    it('writes explicit null and retains it in the snapshot', function () {
        $model = ($this->load)();
        $model->assign('value', null);
        expect($model->changed('value'))->toBe(true);
        expect($this->connection)->toReceive('exec')->with('UPDATE `fixtures_private_tracked` SET `value` = NULL WHERE `id` = 1')->once();
        $model->save(false);
        expect($model->changed('value'))->toBe(false);
        expect(array_key_exists('value', $model->originals()))->toBe(true);
        expect($model->originals()['value'])->toBe(null);
        $model->clear('value');
        expect($model->changed('value'))->toBe(true);
    });

    it('writes initialized values without change filtering when tracking is disabled', function () {
        $prototype = new class extends \Gforces\ActiveRecord\Base {
            #[\Gforces\ActiveRecord\Column] public int $id;
            #[\Gforces\ActiveRecord\Column] private ?string $value;
            #[\Gforces\ActiveRecord\Column] private string $fresh;
            public function changed(string $attribute): bool { return $this->isAttributeChanged($attribute); }
            public static function getTableName(): string { return 'untracked'; }
        };
        $model = ($this->load)($prototype::class);
        expect(fn() => $model->changed('value'))->toThrow(new ActiveRecordException('Changes are not stored for this object'));
        expect($this->connection)->toReceive('exec')->with("UPDATE `untracked` SET `id` = 1, `value` = 'old' WHERE `id` = 1")->once();
        $model->save(false);
    });

    it('rejects an unset persisted primary key before executing SQL', function () {
        $model = ($this->load)();
        unset($model->id);
        expect($model->changed('id'))->toBe(true);
        expect($this->connection)->not->toReceive('exec');
        expect(fn() => $model->save(false))->toThrow(new ActiveRecordException());
    });
});
