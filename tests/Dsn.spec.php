<?php

use Gforces\ActiveRecord\ActiveRecordException;
use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Connection;
use Gforces\ActiveRecord\Connection\Providers\Dsn;
use Kahlan\Plugin\Double;

describe('DSN provider isolation', function () {
    beforeEach(function () {
        $this->savedState = [];
        foreach (['connections', 'connectionProviders'] as $name) {
            $property = new ReflectionProperty(Base::class, $name);
            $this->savedState[$name] = $property->getValue();
            $property->setValue(null, []);
        }
        // Exercise the real provider without opening a database connection.
        allow(Connection::class)->toReceive('__construct')->andRun(function () {});
    });

    afterEach(function () {
        foreach ($this->savedState as $name => $value) {
            (new ReflectionProperty(Base::class, $name))->setValue(null, $value);
        }
    });

    it('forwards connection options without registering a global connection', function () {
        $options = [PDO::ATTR_TIMEOUT => 3];
        expect(Connection::class)->toReceive('__construct')->with('mysql:dbname=model', 'user', 'password', $options)->once();
        $connection = (new Dsn('mysql:dbname=model', 'user', 'password', $options))->getConnection();
        expect($connection)->toBeAnInstanceOf(Connection::class);
        expect(fn() => Base::getConnection())->toThrow(new ActiveRecordException('Connection provider is not set'));
    });

    foreach (['default-first', 'models-first', 'interleaved'] as $order) {
        it("keeps default and model-specific connections separate ($order)", function () use ($order) {
            $first = Double::classname(['extends' => Base::class]);
            $second = Double::classname(['extends' => Base::class]);
            $fallback = Double::classname(['extends' => Base::class]);
            Base::setConnectionProvider(new Dsn('mysql:dbname=default'));
            $first::setConnectionProvider(new Dsn('mysql:dbname=first'));
            $second::setConnectionProvider(new Dsn('mysql:dbname=second'));
            foreach (['default', 'first', 'second'] as $database) {
                expect(Connection::class)->toReceive('__construct')->with("mysql:dbname=$database", null, '', null)->once();
            }
            $classes = match ($order) {
                'default-first' => [Base::class, $first, $second],
                'models-first' => [$first, $second, Base::class],
                'interleaved' => [$first, Base::class, $second],
            };
            $connections = [];
            foreach ($classes as $class) {
                $connections[$class] = $class::getConnection();
            }
            expect($connections[$first])->not->toBe($connections[$second]);
            expect($connections[$first])->not->toBe($connections[Base::class]);
            expect($connections[$second])->not->toBe($connections[Base::class]);
            foreach ($classes as $class) {
                expect($class::getConnection())->toBe($connections[$class]);
            }
            expect($fallback::getConnection())->toBe($connections[Base::class]);
        });
    }

    it('preserves an explicitly configured default connection', function () {
        $default = (new ReflectionClass(Connection::class))->newInstanceWithoutConstructor();
        Base::setConnection($default);
        $model = Double::classname(['extends' => Base::class]);
        $model::setConnectionProvider(new Dsn('mysql:dbname=model'));
        expect($model::getConnection())->not->toBe($default);
        expect(Base::getConnection())->toBe($default);
    });
});
