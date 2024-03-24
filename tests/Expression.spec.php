<?php

use Fixtures\HttpStatusCode;
use Fixtures\Simple;
use Fixtures\State;
use Fixtures\Symbol;
use Gforces\ActiveRecord\PropertyExpression;
use Gforces\ActiveRecord\Connection;
use Gforces\ActiveRecord\Expression;
use Gforces\ActiveRecord\Expressions\Compare;
use Gforces\ActiveRecord\Expressions\In;
use Gforces\ActiveRecord\Expressions\NotIn;
use Kahlan\Plugin\Double;

describe('Expressions', function () {
    given('connection', function () {
        $connection = Double::instance(['class' => Connection::class]);
        allow($connection)->toReceive('quote')->andRun(fn($value) => "'$value'");
        allow($connection)->toReceive('quoteIdentifier')->andRun(fn($identifier) => "`$identifier`");
        return $connection;
    });

    describe('::buildForAttribute()', function () {
        it('builds default Equals expression for scalar, DateTime and Enum values', function () {
            foreach (
                [
                    "`name` = 'John'" => ['name', 'John'],
                    "`age` = 5" => ['age', 5],
                    "`date` = '2024-01-02 12:14:07'" => ['date', new DateTime('2024-01-02 12:14:07')],
                    "`statue` = 'on'" => ['statue', State::on],
                    "`status` = 404" => ['status', HttpStatusCode::notFound],
                    "`symbol` = 'D'" => ['symbol', Symbol::diamonds],
                    "`type` IS NULL" => ['type', null],
                ] as $expected => $params) {
                $expression = Expression::buildForAttribute(...$params);
                expect($expression)->toBeAnInstanceOf(Compare::class);
                $expression->connection = $this->connection;
                expect((string) $expression)->toBe($expected);
            }
        });
        it('builds default In expression for arrays', function () {
            foreach (
                [
                    "`name` IN ('John', 'Bill')" => ['name', ['John', 'Bill']],
                    "`age` IN (5, 10, 15)" => ['age', [5, 10, 15]],
                    "`date` IN ('2024-01-02 12:14:07', '2024-02-01 11:15:00')" => ['date', [new DateTime('2024-01-02 12:14:07'), new DateTime('2024-02-01 11:15:00')]],
                    "`statue` IN ('on', 'off')" => ['statue', [State::on, State::off]],
                    "`status` IN (404)" => ['status', [HttpStatusCode::notFound]],
                    "`symbol` IN ('D', 'S')" => ['symbol', [Symbol::diamonds, Symbol::spades]],
                ] as $expected => $params) {
                $expression = Expression::buildForAttribute(...$params);
                expect($expression)->toBeAnInstanceOf(In::class);
                $expression->connection = $this->connection;
                expect((string) $expression)->toBe($expected);
            }
        });

        it('builds other compare expressions', function () {
            foreach (
                [
                    "`name` <> 'John'" => ['name', PropertyExpression::ne('John')],
                    "`age` > 5" => ['age', PropertyExpression::gt(5)],
                    "`date` < '2024-01-02 12:14:07'" => ['date', PropertyExpression::lt(new DateTime('2024-01-02 12:14:07'))],
                    "`age` >= 10" => ['age', PropertyExpression::ge(10)],
                    "`age` <= 10" => ['age', PropertyExpression::le(10)],
                    "`type` IS NOT NULL" => ['type', PropertyExpression::ne(null)],
                ] as $expected => $params) {
                $expression = Expression::buildForAttribute(...$params);
                expect($expression)->toBeAnInstanceOf(Compare::class);
                $expression->connection = $this->connection;
                expect((string) $expression)->toBe($expected);
            }
        });

        it('builds NotIn expression', function () {
            foreach (
                [
                    "`age` NOT IN (5, 10, 15)" => ['age', PropertyExpression::notIn([5, 10, 15])],
                ] as $expected => $params) {
                $expression = Expression::buildForAttribute(...$params);
                expect($expression)->toBeAnInstanceOf(NotIn::class);
                $expression->connection = $this->connection;
                expect((string) $expression)->toBe($expected);
            }
        });
    });

    describe('Group OR expressions with parentheses', function () {
        it('adds parentheses', function () {
            allow(Simple::class)->toReceive('::getConnection')->andReturn($this->connection);
            allow(Simple::class)->toReceive('::findAllBySql')->andReturn([]);
            expect(Simple::class)->toReceive('::findAllBySql')->with("SELECT * FROM `fixtures_simple` WHERE (`name` = 'Bill' OR `age` > 5) AND `size` = 5")->once();
            Simple::findAll([
                Expression::or([
                    'name' => 'Bill',
                    'age' => PropertyExpression::gt(5),
                ]),
                'size' => 5,
            ]);
        });
    });
});
