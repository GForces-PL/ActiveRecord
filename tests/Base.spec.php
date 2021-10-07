<?php

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Connection;
use Gforces\ActiveRecord\Exception;
use Kahlan\Plugin\Double;

function setBaseProperty(string $property, mixed $value)
{
    $class = new ReflectionClass(Base::class);
    $property = $class->getProperty($property);
    $property->setAccessible(true);
    $property->setValue($value);
}

describe(Base::class, function () {
    beforeEach(function () {
        $this->modelClass = Double::classname(['extends' => Base::class]);
        $this->model = new $this->modelClass;
    });

    given('connection', function () {
        $connection = Double::instance(['class' => Connection::class]);
        allow($this->modelClass)->toReceive('::getQuotedTableName')->andReturn('`table`');
        allow($this->modelClass)->toReceive('::getConnection')->andReturn($connection);
        allow($connection)->toReceive('quote')->andRun(fn($value) => "'$value'");
        allow($connection)->toReceive('quoteIdentifier')->andRun(fn($identifier) => "`$identifier`");
        return $connection;
    });

    given('statement', function () {
        $statement = Double::instance(['class' => Connection\Statement::class]);
        allow($this->connection)->toReceive('query')->andReturn($statement);
        return $statement;
    });

    describe('::find()', function () {
        it('allows find object with id', function () {
            allow($this->modelClass)->toReceive('::findFirstByAttribute')->with('id', 1)->andReturn($this->model);
            expect($this->modelClass::find(1))->toBe($this->model);
        });
        it('throws exception when object not found', function () {
            allow($this->modelClass)->toReceive('::findFirstByAttribute')->with('id', 1)->andReturn(null);
            expect(fn() => $this->modelClass::find(1))->toThrow(new Exception("object with id 1 of type $this->modelClass not found"));
        });
        it('generates valid query', function () {
            allow($this->statement)->toReceive('fetchAll')->andReturn([$this->model]);
            expect($this->connection)->toReceive('query')->with("SELECT * FROM `table` WHERE `id` = 100 LIMIT 1");
            $this->modelClass::find(100);
        });
    });

    describe('::findAll()', function () {
        it('it returns all objects using built query', function () {
            allow($this->modelClass)->toReceive('::buildQuery')->andReturn('query');
            allow($this->modelClass)->toReceive('::findAllBySql')->with('query')->andReturn([1, 2, 3]);
            expect($this->modelClass)->toReceive('::buildQuery')->with('', 'id ASC', null, null, '*');
            expect($this->modelClass::findAll())->toBe([1, 2, 3]);
        });
        it('generates valid query to find all object with default order', function () {
            allow($this->statement)->toReceive('fetchAll')->andReturn([$this->model]);
            expect($this->connection)->toReceive('query')->with("SELECT * FROM `table` ORDER BY id ASC");
            $this->modelClass::findAll();
        });
    });

    describe('::findFirst()', function () {
        it('it returns only first object using built query', function () {
            allow($this->modelClass)->toReceive('::buildQuery')->andReturn('query');
            allow($this->modelClass)->toReceive('::findAllBySql')->with('query')->andReturn([$this->model, new $this->modelClass]);
            expect($this->modelClass::findFirst())->toBe($this->model);
        });
        it('it returns null if no results', function () {
            allow($this->modelClass)->toReceive('::buildQuery')->andReturn('query');
            allow($this->modelClass)->toReceive('::findAllBySql')->with('query')->andReturn([]);
            expect($this->modelClass::findFirst())->toBe(null);
        });
        it('adds limit 1 to query', function () {
            allow($this->modelClass)->toReceive('::buildQuery')->with('criteria', 'order', 1)->andReturn('query');
            allow($this->modelClass)->toReceive('::findAllBySql')->with('query')->andReturn([$this->model, new $this->modelClass]);
            expect($this->modelClass::findFirst('criteria', 'order'))->toBe($this->model);
        });
        it('generates valid query with criteria and order', function () {
            allow($this->statement)->toReceive('fetchAll')->andReturn([$this->model]);
            expect($this->connection)->toReceive('query')->with("SELECT * FROM `table` WHERE criteria ORDER BY custom_order LIMIT 1");
            $this->modelClass::findFirst('criteria', 'custom_order');
        });
    });

    describe('::findFirstByAttribute()', function () {
        it('it uses condition builder and ::findFirst() method', function () {
            allow($this->modelClass)->toReceive('::condition')->with('name', 'value')->andReturn('criteria');
            allow($this->modelClass)->toReceive('::findFirst')->with('criteria', 'order')->andReturn($this->model);
            expect($this->modelClass::findFirstByAttribute('name', 'value', 'order'))->toBe($this->model);
        });
        it('generates valid query', function () {
            allow($this->statement)->toReceive('fetchAll')->andReturn([$this->model]);
            expect($this->connection)->toReceive('query')->with("SELECT * FROM `table` WHERE `name` = 'value' LIMIT 1");
            $this->modelClass::findFirstByAttribute('name', 'value');
        });
    });

    describe('::findFirstByAttributes()', function () {
        it('it uses multiple condition builders and ::findFirst() method', function () {
            allow($this->modelClass)->toReceive('::condition')->with('name', 'value1')->andReturn('criteria1');
            allow($this->modelClass)->toReceive('::condition')->with('key', 'value2')->andReturn('criteria2');
            allow($this->modelClass)->toReceive('::findFirst')->with('criteria1 AND criteria2', 'order')->andReturn($this->model);
            expect($this->modelClass::findFirstByAttributes(['name' => 'value1', 'key' => 'value2'], 'order'))->toBe($this->model);
        });
        it('generates valid query', function () {
            allow($this->statement)->toReceive('fetchAll')->andReturn([$this->model]);
            expect($this->connection)->toReceive('query')->with("SELECT * FROM `table` WHERE `name` = 'Smith' AND `age` = 20 AND `role` IS NULL LIMIT 1");
            $this->modelClass::findFirstByAttributes(['name' => 'Smith', 'age' => 20, 'role' => null]);
        });
    });

    describe('::findAllBySql()', function () {
        it('it calls PDO prepare and execute methods for results', function () {
            allow($this->statement)->toReceive('fetchAll')->with(PDO::FETCH_CLASS, $this->modelClass)->andReturn([$this->model]);
            expect($this->connection)->toReceive('query')->with('query');
            expect($this->modelClass::findAllBySql('query'))->toBe([$this->model]);
        });
    });

    describe('::count()', function () {
        it('it calls PDO query built with count(*)', function () {
            allow($this->modelClass)->toReceive('::buildQuery')->andReturn('query');
            allow($this->statement)->toReceive('fetchColumn')->andReturn(100);
            expect($this->modelClass)->toReceive('::buildQuery')->with('criteria', '', null, null, 'COUNT(*)');
            expect($this->connection)->toReceive('query')->with('query');
            expect($this->modelClass::count('criteria'))->toBe(100);
        });
        it('generates valid query', function () {
            allow($this->statement)->toReceive('fetchColumn')->andReturn(100);
            expect($this->connection)->toReceive('query')->with("SELECT COUNT(*) FROM `table` WHERE criteria");
            $this->modelClass::count('criteria');
        });
    });

    describe('::insert()', function () {
        it('it execute INSERT command', function () {
            expect($this->connection)->toReceive('exec')->with("INSERT INTO `table` (`name`,`age`,`role`) VALUES ('Smith',20,NULL)");
            $this->modelClass::insert(['name' => 'Smith', 'age' => 20, 'role' => null]);
        });
        it('it ads IGNORE to INSERT command', function () {
            expect($this->connection)->toReceive('exec')->with("INSERT IGNORE INTO `table` (`name`,`age`,`role`) VALUES ('Smith',20,NULL)");
            $this->modelClass::insert(['name' => 'Smith', 'age' => 20, 'role' => null], true);
        });
        it('it ads ON DUPLICATE KEY UPDATE to INSERT command', function () {
            expect($this->connection)->toReceive('exec')->with("INSERT INTO `table` (`name`,`age`,`role`) VALUES ('Smith',20,NULL) ON DUPLICATE KEY UPDATE `name` = 'Jones'");
            $this->modelClass::insert(['name' => 'Smith', 'age' => 20, 'role' => null], onDuplicateKeyUpdate: "`name` = 'Jones'");
        });
    });

    describe('::updateAll()', function () {
        it('it do nothing with empty attributes', function () {
            expect($this->modelClass)->not->toReceive('::getQuotedTableName');
            expect($this->modelClass)->not->toReceive('::getConnection');
            $this->modelClass::updateAll([]);
        });
        it('it execute UPDATE command without condition', function () {
            expect($this->connection)->toReceive('exec')->with("UPDATE `table` SET `name` = 'Smith', `age` = 20, `role` = NULL");
            $this->modelClass::updateAll(['name' => 'Smith', 'age' => 20, 'role' => null]);
        });
    });

    describe('::setConnection', function () {
        it('allows to set connections', function() {
            $this->modelClass::setConnection($connection = Double::instance(['class' => Connection::class]));
            expect($this->modelClass::getConnection())->toBe($connection);
        });
        it('doesn\'t set connection to Base and other classes', function() {
            $this->modelClass::setConnection(Double::instance(['class' => Connection::class]));
            $otherClass = Double::classname(['extends' => Base::class]);
            expect(fn() => $otherClass::getConnection())->toThrow(new Exception('Connection provider is not set'));
            expect(fn() => Base::getConnection())->toThrow(new Exception('Connection provider is not set'));
        });
    });

    describe('::getConnection', function () {
        it('throws exception when connection is not set at all', function() {
            expect(fn() => $this->modelClass::getConnection())->toThrow(new Exception('Connection provider is not set'));
        });
        it('returns base connection if own is not set', function() {
            setBaseProperty('connections', [Base::class => $connection = Double::instance(['class' => Connection::class])]);
            expect($this->modelClass::getConnection())->toBe($connection);
            setBaseProperty('connections', []);
        });
        it('returns own connection even base connection is set', function() {
            setBaseProperty('connections', [
                Base::class => $baseConnection = Double::instance(['class' => Connection::class]),
                $this->modelClass => $ownConnection = Double::instance(['class' => Connection::class]),
            ]);
            expect($this->modelClass::getConnection())->toBe($ownConnection);
            setBaseProperty('connections', []);
        });
        it('it throws exception even it is set for different class', function() {
            $otherClass = Double::classname(['extends' => Base::class]);
            $otherClass::setConnection(Double::instance(['class' => Connection::class]));
            expect(fn() => $this->modelClass::getConnection())->toThrow(new Exception('Connection provider is not set'));
        });
        it('returns connection from own provider if both own and base connection are not set', function() {
            $provider = Double::instance(['implements' => Connection\Providers\Provider::class]);
            allow($provider)->toReceive('getConnection')->andReturn($connection = Double::instance(['class' => Connection::class]));
            $this->modelClass::setConnectionProvider($provider);
            expect($this->modelClass::getConnection())->toBe($connection);
        });
        it('returns connection from base provider', function() {
            setBaseProperty('connectionProviders', [Base::class => $provider = Double::instance(['implements' => Connection\Providers\Provider::class])]);
            allow($provider)->toReceive('getConnection')->andReturn($connection = Double::instance(['class' => Connection::class]));
            expect($this->modelClass::getConnection())->toBe($connection);
        });
    });

    describe('::getTableName', function () {
        it('returns table name based on model class', function () {
            $tests = [
                'Vehicle' => 'vehicle',
                'OwnerVehicle' => 'owner_vehicle',
            ];
            foreach ($tests as $class => $table) {
                expect(Double::classname(['class' => $class, 'extends' => Base::class])::getTableName())->toBe($table);
            }
        });
    });

    describe('::condition', function () {
        it('it builds correct condition for various types', function () {
            $this->connection;
            $class = new ReflectionClass($this->modelClass);
            $method = $class->getMethod('condition');
            $method->setAccessible(true);
            $tests = [
                "`name` = 'Smith'" => ['name', 'Smith'],
                "`age` = 10" => ['age', 10],
                "`role` IS NULL" => ['role', null],
                "`new` = 1" => ['new', true],
                "`name` IN ('Smith', 'Jones', 'Williams')" => ['name', ['Smith', 'Jones', 'Williams']],
            ];
            foreach ($tests as $result => $params) {
                expect($method->invoke(null, ...$params))->toBe($result);
            }
        });
    });
});
