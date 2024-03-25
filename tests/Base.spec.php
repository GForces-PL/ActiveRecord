<?php

use Fixtures\HttpStatusCode;
use Fixtures\Simple;
use Fixtures\State;
use Fixtures\Symbol;
use Fixtures\WithBackedEnumProperty;
use Fixtures\WithDateTimeProperty;
use Fixtures\WithUnitEnumProperty;
use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;
use Gforces\ActiveRecord\Connection;
use Gforces\ActiveRecord\Expression;
use Kahlan\Plugin\Double;

/**
 * @throws ActiveRecordException
 */
function setBaseProperty(string $class, string $property, mixed $value): void
{
    try {
        $class = new ReflectionClass($class);
        $property = $class->getProperty($property);
        $property->setValue(null, $value);
    } catch (ReflectionException $e) {
        throw new ActiveRecordException($e->getMessage(), $e->getCode(), $e);
    }
}

describe(Base::class, function () {
    given('modelClass', function() {
        /** @var class-string<Base> $class */
        $class = Double::classname(['extends' => Base::class]);
        allow($class)->toReceive('::getTableName')->andReturn('table');
        $class::setConnection($this->connection);
        return $class;
    });

    given('model', fn () => new $this->modelClass);

    given('connection', function () {
        $connection = Double::instance(['class' => Connection::class]);
        allow($connection)->toReceive('quote')->andRun(fn($value) => "'$value'");
        allow($connection)->toReceive('quoteIdentifier')->andRun(fn($identifier) => "`$identifier`");
        return $connection;
    });

    given('statement', fn () => Double::instance(['class' => Connection\Statement::class]));

    describe('::find()', function () {
        it('uses findFirstByAttribute', function () {
            allow($this->modelClass)->toReceive('::findFirstByAttribute')->andReturn($this->model);
            expect($this->modelClass)->toReceive('::findFirstByAttribute')->with('id', 100)->once();
            $this->modelClass::find(100);
        });
        it('allows find object with id', function () {
            allow($this->modelClass)->toReceive('::findFirstByAttribute')->with('id', 1)->andReturn($this->model);
            expect($this->modelClass::find(1))->toBe($this->model);
        });
        it('throws exception when object not found', function () {
            allow($this->modelClass)->toReceive('::findFirstByAttribute')->with('id', 1)->andReturn(null);
            expect(fn() => $this->modelClass::find(1))->toThrow(new ActiveRecordException("object with id 1 of type $this->modelClass not found"));
        });
    });

    describe('::findAll()', function () {
        it('returns all objects using query builder', function () {
            allow($this->modelClass)->toReceive('::buildQuery')->andReturn('query');
            allow($this->modelClass)->toReceive('::findAllBySql')->with('query')->andReturn([1, 2, 3]);
            expect($this->modelClass)->toReceive('::buildQuery')->with('', '', null, null, '*');
            expect($this->modelClass::findAll())->toBe([1, 2, 3]);
        });
        it('generates valid query to find all object without other params', function () {
            allow($this->modelClass)->toReceive('::findAllBySql')->andReturn([]);
            expect($this->modelClass)->toReceive('::findAllBySql')->with("SELECT * FROM `table`")->once();
            $this->modelClass::findAll();
        });
        it('allows set $criteria, $orderBy, $limit, $offset and $select params', function () {
            $query = "SELECT * FROM `table` WHERE criteria ORDER BY id LIMIT 100 OFFSET 200";
            allow($this->modelClass)->toReceive('::findAllBySql')->andReturn([]);
            expect($this->modelClass)->toReceive('::findAllBySql')->with($query)->once();
            $this->modelClass::findAll('criteria', 'id', 100, 200);
        });
    });

    describe('::findFirst()', function () {
        it('uses ::findAll with limit 1', function() {
            allow($this->modelClass)->toReceive('::findAll')->with('', '', 1, null)->andReturn([]);
            expect($this->modelClass)->toReceive('::findAll')->with('', '', 1, null)->once();
            $this->modelClass::findFirst();
        });
        it('allows set $criteria, $orderBy and $offset params', function () {
            allow($this->modelClass)->toReceive('::findAll')->with('criteria', 'id', 1, 100)->andReturn([]);
            expect($this->modelClass)->toReceive('::findAll')->with('criteria', 'id', 1, 100)->once();
            $this->modelClass::findFirst('criteria', 'id', 100);
        });

        it('it returns only first object when available', function () {
            allow($this->modelClass)->toReceive('::findAll')->andReturn([$this->model]);
            expect($this->modelClass::findFirst())->toBe($this->model);
        });
        it('it returns null if no results', function () {
            allow($this->modelClass)->toReceive('::findAll')->andReturn([]);
            expect($this->modelClass::findFirst())->toBe(null);
        });
    });

    describe('::findFirstByAttribute()', function () {
        it('it uses condition builder and ::findFirst() method', function () {
            allow($this->modelClass)->toReceive('::condition')->with('name', 'value')->andReturn('criteria');
            allow($this->modelClass)->toReceive('::findFirst')->with('criteria', 'order')->andReturn($this->model);
            expect($this->modelClass::findFirstByAttribute('name', 'value', 'order'))->toBe($this->model);
        });
    });

    describe('::findFirstByAttributes()', function () {
        it('it uses ::findFirst() method', function () {
            allow($this->modelClass)->toReceive('::findFirst');
            expect($this->modelClass)->toReceive('::findFirst')->with(['name' => 'value1', 'key' => 'value2'], 'order')->once();
            $this->modelClass::findFirstByAttributes(['name' => 'value1', 'key' => 'value2'], 'order');
        });
    });

    describe('::findAllBySql()', function () {
        it('it return objects from database rows', function () {
            $rows = [
                (object) ['id' => 100],
                (object) ['id' => 300],
                (object) ['id' => 200],
            ];
            allow($this->connection)->toReceive('query')->with('query')->andReturn($this->statement);
            allow($this->statement)->toReceive('fetchObject')->andReturn(...[...$rows, null]);

            Simple::setConnection($this->connection);

            $models = Simple::findAllBySql('query');
            expect(count($models))->toBe(3);
            expect($models[0]->id)->toBe(100);
            expect($models[1]->id)->toBe(300);
            expect($models[2]->id)->toBe(200);
            expect($models[0])->toBeAnInstanceOf(Simple::class);
        });
        it('omits data columns which are not model properties', function () {
            $query = "SELECT id, 'custom_value' as 'custom_column' FROM `simple`";
            $row = (object) ['id' => 1, 'custom_column' => 'custom_value'];

            allow($this->connection)->toReceive('query')->with($query)->andReturn($this->statement);
            allow($this->statement)->toReceive('fetchObject')->andReturn($row, null);
            Simple::setConnection($this->connection);
            expect(fn() => Simple::findAllBySql($query))->not->toThrow(new ActiveRecordException);
//            $model = Simple::findAllBySql($query)[0];
        });
    });

    describe('::count()', function () {
        it('it calls PDO query with count(*)', function () {
            allow($this->modelClass)->toReceive('::buildQuery')->andReturn('query');
            allow($this->connection)->toReceive('query')->with('query')->andReturn($this->statement);
            allow($this->statement)->toReceive('fetchColumn')->andReturn(100);
            expect($this->modelClass)->toReceive('::buildQuery')->with('criteria', '', null, null, 'COUNT(*)');
            expect($this->connection)->toReceive('query')->with('query');
            expect($this->modelClass::count('criteria'))->toBe(100);
        });
    });

    describe('::exists()', function () {
        it('it returns true if EXISTS query returns 1', function () {
            allow($this->modelClass)->toReceive('::buildQuery')->andReturn('query');
            allow($this->connection)->toReceive('query')->with('SELECT EXISTS(query)')->andReturn($this->statement);
            expect($this->modelClass)->toReceive('::buildQuery')->with('criteria');
            expect($this->connection)->toReceive('query')->with('SELECT EXISTS(query)');

            allow($this->statement)->toReceive('fetchColumn')->andReturn(1);
            expect($this->modelClass::exists('criteria'))->toBe(true);
        });
        it('it returns false if EXISTS query returns 0', function () {
            allow($this->modelClass)->toReceive('::buildQuery')->andReturn('query');
            allow($this->connection)->toReceive('query')->with('SELECT EXISTS(query)')->andReturn($this->statement);
            expect($this->modelClass)->toReceive('::buildQuery')->with('criteria');
            expect($this->connection)->toReceive('query')->with('SELECT EXISTS(query)');

            allow($this->statement)->toReceive('fetchColumn')->andReturn(0);
            expect($this->modelClass::exists('criteria'))->toBe(false);
        });
    });

    describe('::insert()', function () {
        it('it execute INSERT command', function () {
            allow($this->connection)->toReceive('exec')->andReturn(1);
            expect($this->connection)->toReceive('exec')->with("INSERT INTO `table` (`name`,`age`,`role`) VALUES ('Smith',20,NULL)");
            $this->modelClass::insert(['name' => 'Smith', 'age' => 20, 'role' => null]);
        });
        it('it ads IGNORE to INSERT command', function () {
            allow($this->connection)->toReceive('exec')->andReturn(1);
            expect($this->connection)->toReceive('exec')->with("INSERT IGNORE INTO `table` (`name`,`age`,`role`) VALUES ('Smith',20,NULL)");
            $this->modelClass::insert(['name' => 'Smith', 'age' => 20, 'role' => null], true);
        });
        it('it ads ON DUPLICATE KEY UPDATE to INSERT command', function () {
            allow($this->connection)->toReceive('exec')->andReturn(1);
            expect($this->connection)->toReceive('exec')->with("INSERT INTO `table` (`name`,`age`,`role`) VALUES ('Smith',20,NULL) ON DUPLICATE KEY UPDATE `name` = 'Jones'");
            $this->modelClass::insert(['name' => 'Smith', 'age' => 20, 'role' => null], onDuplicateKeyUpdate: "`name` = 'Jones'");
        });
        it('allows to use Expression in attributes', function () {
            allow($this->connection)->toReceive('exec')->andReturn(1);
            expect($this->connection)->toReceive('exec')->with("INSERT INTO `table` (`updated_at`) VALUES (NOW())");
            $this->modelClass::insert(['updated_at' => Expression::now()]);
        });
    });

    describe('::updateAll()', function () {
        it('it does nothing with empty attributes', function () {
            expect($this->modelClass)->not->toReceive('::getQuotedTableName');
            expect($this->modelClass)->not->toReceive('::getConnection');
            $this->modelClass::updateAll([]);
        });
        it('it executes UPDATE command without condition', function () {
            allow($this->connection)->toReceive('exec')->andReturn(1);
            expect($this->connection)->toReceive('exec')->with("UPDATE `table` SET `name` = 'Smith', `age` = 20, `role` = NULL");
            $this->modelClass::updateAll(['name' => 'Smith', 'age' => 20, 'role' => null]);
        });
        it('allows to use Expression in attributes', function () {
            allow($this->connection)->toReceive('exec')->andReturn(1);
            expect($this->connection)->toReceive('exec')->with("UPDATE `table` SET `updated_at` = NOW()");
            $this->modelClass::updateAll(['updated_at' => Expression::now()]);
        });
        it('allows to set criteria', function () {
            allow($this->connection)->toReceive('exec')->andReturn(1);
            expect($this->connection)->toReceive('exec')->with("UPDATE `table` SET `attribute` = 'value' WHERE criteria");
            $this->modelClass::updateAll(['attribute' => 'value'], 'criteria');
        });
    });

    describe('::deleteAll()', function () {
        it('it executes DELETE command without condition', function () {
            allow($this->connection)->toReceive('exec')->andReturn(1);
            expect($this->connection)->toReceive('exec')->with("DELETE FROM `table`");
            $this->modelClass::deleteAll();
        });
        it('allows to set criteria', function () {
            allow($this->connection)->toReceive('exec')->andReturn(1);
            expect($this->connection)->toReceive('exec')->with("DELETE FROM `table` WHERE criteria");
            $this->modelClass::deleteAll('criteria');
        });
    });

    describe('::setConnection', function () {
        it('allows to set connections', function () {
            $connection = Double::instance(['class' => Connection::class]);
            $modelClass = $this->modelClass;
            $modelClass::setConnection($connection);
            expect($modelClass::getConnection())->toBe($connection);
        });
        it('does not set connection to Base and other classes', function () {
            $this->modelClass::setConnection(Double::instance(['class' => Connection::class]));

            $otherClass = Double::classname(['extends' => Base::class]);
            expect(fn() => $otherClass::getConnection())->toThrow(new ActiveRecordException('Connection provider is not set'));
            expect(fn() => Base::getConnection())->toThrow(new ActiveRecordException('Connection provider is not set'));
        });
    });

    describe('::getConnection', function () {
        afterEach(function() {
            setBaseProperty(Base::class,'connections', []);
            setBaseProperty(Base::class, 'connectionProviders', []);
        });
        given('modelClass', function() {
            return Double::classname(['extends' => Base::class]);
        });
        it('throws exception when connection is not set at all', function () {
            expect(fn() => $this->modelClass::getConnection())->toThrow(new ActiveRecordException('Connection provider is not set'));
        });
        it('returns base connection if own is not set', function () {
            Base::setConnection($connection = Double::instance(['class' => Connection::class]));
            expect($this->modelClass::getConnection())->toBe($connection);
        });
        it('returns own connection even base connection is set', function () {
            Base::setConnection($baseConnection = Double::instance(['class' => Connection::class]));
            $this->modelClass::setConnection($ownConnection = Double::instance(['class' => Connection::class]));
            expect($this->modelClass::getConnection())->toBe($ownConnection);
        });
        it('it throws exception even it is set for different class', function () {
            $otherClass = Double::classname(['extends' => Base::class]);
            $otherClass::setConnection(Double::instance(['class' => Connection::class]));
            expect(fn() => $this->modelClass::getConnection())->toThrow(new ActiveRecordException('Connection provider is not set'));
        });
        it('returns connection from own provider if both own and base connection are not set', function () {
            $provider = Double::instance(['implements' => Connection\Providers\Provider::class]);
            allow($provider)->toReceive('getConnection')->andReturn($connection = Double::instance(['class' => Connection::class]));
            $this->modelClass::setConnectionProvider($provider);
            expect($this->modelClass::getConnection())->toBe($connection);
        });
        it('returns connection from base provider', function () {
            Base::setConnectionProvider($provider = Double::instance(['implements' => Connection\Providers\Provider::class]));
            allow($provider)->toReceive('getConnection')->andReturn($connection = Double::instance(['class' => Connection::class]));
            expect($this->modelClass::getConnection())->toBe($connection);
        });
        it('returns the same connection for multiple classes if Base connection is set', function () {
            Base::setConnectionProvider($provider = Double::instance(['implements' => Connection\Providers\Provider::class]));
            allow($provider)->toReceive('getConnection')->andReturn($connection = Double::instance(['class' => Connection::class]));
            expect($this->modelClass::getConnection())->toBe($connection);
            $otherClass = Double::classname(['extends' => Base::class]);
            allow($provider)->toReceive('getConnection')->andReturn(Double::instance(['class' => Connection::class]));
            expect($otherClass::getConnection())->toBe($connection);
        });
    });

    describe('::getTableName', function () {
        it('returns table name based on model class', function () {
            $tests = [
                'Vehicle' => 'vehicle',
                'VehicleOwner' => 'vehicle_owner',
            ];
            foreach ($tests as $class => $table) {
                expect(Double::classname(['class' => $class, 'extends' => Base::class])::getTableName())->toBe($table);
            }
        });
        it('returns table name based on model class with namespace', function () {
            $tests = [
                'Vehicle\\Model' => 'vehicle_model',
                'Vehicle\\EngineSize' => 'vehicle_engine_size',
            ];
            foreach ($tests as $class => $table) {
                expect(Double::classname(['class' => $class, 'extends' => Base::class])::getTableName())->toBe($table);
            }
        });
        it('removed models class prefix', function () {
            Base::$modelsNamespacePrefix = 'Models\\';
            $tests = [
                'Models\\Vehicle\\Model' => 'vehicle_model',
                'Models\\Vehicle\\EngineSize' => 'vehicle_engine_size',
            ];
            foreach ($tests as $class => $table) {
                expect(Double::classname(['class' => $class, 'extends' => Base::class])::getTableName())->toBe($table);
            }
            Base::$modelsNamespacePrefix = '';
        });
    });

    describe('::condition', function () {
        it('it builds correct condition for various types', function () {
            $class = new ReflectionClass($this->modelClass);
            $method = $class->getMethod('condition');
            $tests = [
                "`name` = 'Smith'" => ['name', 'Smith'],
                "`age` = 10" => ['age', 10],
                "`role` IS NULL" => ['role', null],
                "`new` = 1" => ['new', true],
                "`name` IN ('Smith', 'Jones', 'Williams')" => ['name', ['Smith', 'Jones', 'Williams']],
                "`updated_at` = NOW()" => ['updated_at', Expression::now()],
                "`statue` = 'on'" => ['statue', State::on],
                "`status` = 404" => ['status', HttpStatusCode::notFound],
                "`symbol` = 'D'" => ['symbol', Symbol::diamonds],
                "`date` = '2000-05-05 05:05:05'" => ['date', new DateTime('2000-05-05 05:05:05')],
            ];
            foreach ($tests as $result => $params) {
                expect($method->invoke(null, ...$params))->toBe($result);
            }
        });
    });

    describe('UnitEnum property', function() {
        it('can load object with default values', function() {
            $rows = [
                (object) ['id' => '1'],
            ];
            allow($this->connection)->toReceive('query')->andReturn($this->statement);
            allow($this->statement)->toReceive('fetchObject')->andReturn(...[...$rows, null]);
            WithUnitEnumProperty::setConnection($this->connection);

            $models = WithUnitEnumProperty::findAllBySql('query');
            expect($models[0]->stateDefaultNull)->toBe(null);
            expect($models[0]->stateDefaultOff)->toBe(State::off);
        });

        it('can load object with correct values', function() {
            $rows = [
                (object) ['id' => '2', 'state' => 'on', 'nullableState' => 'on', 'stateDefaultNull' => 'on', 'stateDefaultOff' => 'on'],
            ];
            allow($this->connection)->toReceive('query')->andReturn($this->statement);
            allow($this->statement)->toReceive('fetchObject')->andReturn(...[...$rows, null]);
            WithUnitEnumProperty::setConnection($this->connection);

            $models = WithUnitEnumProperty::findAllBySql('query');
            expect($models[0]->state)->toBe(State::on);
            expect($models[0]->nullableState)->toBe(State::on);
            expect($models[0]->stateDefaultNull)->toBe(State::on);
            expect($models[0]->stateDefaultOff)->toBe(State::on);
        });

        it('fails when load object with invalid values', function() {
            $rows = [
                (object) ['id' => '2', 'state' => 'invalid'],
            ];
            allow($this->connection)->toReceive('query')->andReturn($this->statement);
            allow($this->statement)->toReceive('fetchObject')->andReturn(...[...$rows, null]);
            WithUnitEnumProperty::setConnection($this->connection);

            expect(fn() => WithUnitEnumProperty::findAllBySql('query'))->toThrow(new ActiveRecordException);
        });
    });

    describe('BackedEnum property', function() {
        it('can load object with default values', function() {
            $rows = [
                (object) ['id' => '1'],
            ];
            allow($this->connection)->toReceive('query')->andReturn($this->statement);
            allow($this->statement)->toReceive('fetchObject')->andReturn(...[...$rows, null]);
            WithBackedEnumProperty::setConnection($this->connection);

            $models = WithBackedEnumProperty::findAllBySql('query');
            expect($models[0]->statusDefaultNull)->toBe(null);
            expect($models[0]->statusDefaultOk)->toBe(HttpStatusCode::ok);
        });

        it('can load object with correct values', function() {
            $rows = [
                (object) ['id' => '2', 'status' => '404', 'nullableStatus' => '404', 'statusDefaultNull' => '404', 'statusDefaultOk' => '404'],
            ];
            allow($this->connection)->toReceive('query')->andReturn($this->statement);
            allow($this->statement)->toReceive('fetchObject')->andReturn(...[...$rows, null]);
            WithBackedEnumProperty::setConnection($this->connection);

            $models = WithBackedEnumProperty::findAllBySql('query');
            expect($models[0]->status)->toBe(HttpStatusCode::notFound);
            expect($models[0]->nullableStatus)->toBe(HttpStatusCode::notFound);
            expect($models[0]->statusDefaultNull)->toBe(HttpStatusCode::notFound);
            expect($models[0]->statusDefaultOk)->toBe(HttpStatusCode::notFound);
        });

        it('fails when load object with invalid values', function() {
            $rows = [
                (object) ['id' => '2', 'status' => '500'],
            ];
            allow($this->connection)->toReceive('query')->andReturn($this->statement);
            allow($this->statement)->toReceive('fetchObject')->andReturn(...[...$rows, null]);
            WithBackedEnumProperty::setConnection($this->connection);

            expect(fn() => WithBackedEnumProperty::findAllBySql('query'))->toThrow(new ActiveRecordException);
        });
    });

    describe('DateTime property', function() {
        it('can load object with default values', function() {
            $rows = [
                (object) ['id' => '1'],
            ];
            allow($this->connection)->toReceive('query')->andReturn($this->statement);
            allow($this->statement)->toReceive('fetchObject')->andReturn(...[...$rows, null]);
            WithDateTimeProperty::setConnection($this->connection);

            $models = WithDateTimeProperty::findAllBySql('query');
            expect($models[0]->dateDefaultNull)->toBe(null);
        });

        it('can load object with correct values', function() {
            $rows = [
                (object) ['id' => '2', 'date' => '2000-01-01', 'nullableDate' => '2000-02-02', 'dateDefaultNull' => '2000-03-03'],
            ];
            allow($this->connection)->toReceive('query')->andReturn($this->statement);
            allow($this->statement)->toReceive('fetchObject')->andReturn(...[...$rows, null]);
            WithDateTimeProperty::setConnection($this->connection);

            $models = WithDateTimeProperty::findAllBySql('query');
            expect($models[0]->date->format('Y-m-d'))->toBe('2000-01-01');
            expect($models[0]->nullableDate->format('Y-m-d'))->toBe('2000-02-02');
            expect($models[0]->dateDefaultNull->format('Y-m-d'))->toBe('2000-03-03');
        });

        it('fails when load object with invalid values', function() {
            $rows = [
                (object) ['id' => '2', 'date' => 'invalid'],
            ];
            allow($this->connection)->toReceive('query')->andReturn($this->statement);
            allow($this->statement)->toReceive('fetchObject')->andReturn(...[...$rows, null]);
            WithDateTimeProperty::setConnection($this->connection);

            expect(fn() => WithDateTimeProperty::findAllBySql('query'))->toThrow(new ActiveRecordException);
        });
    });
});
