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
        $connection = Double::instance(['extends' => Connection::class, 'methods' => ['__construct'], 'layer' => true]);
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
    describe('Nested condition arrays', function () {
        beforeEach(function () {
            allow(Simple::class)->toReceive('::getConnection')->andReturn($this->connection);
        });

        it('combines nested groups with AND without losing repeated column names', function () {
            expect(Simple::buildQuery([
                ['id' => 7],
                ['id' => PropertyExpression::notIn([8, 9])],
            ]))->toBe('SELECT * FROM `fixtures_simple` WHERE `id` = 7 AND `id` NOT IN (8, 9)');
        });

        it('treats each array inside OR as an AND group', function () {
            expect(Simple::buildQuery([
                Expression::or([
                    ['name' => 'Bill', 'age' => 5],
                    ['name' => 'John', 'age' => 10],
                ]),
                'enabled' => true,
            ]))->toBe("SELECT * FROM `fixtures_simple` WHERE (`name` = 'Bill' AND `age` = 5 OR `name` = 'John' AND `age` = 10) AND `enabled` = 1");
        });

        it('preserves explicit OR parentheses inside several levels of arrays', function () {
            expect(Simple::buildQuery([
                ['enabled' => true, [
                    Expression::or([
                        ['age' => PropertyExpression::lt(18)],
                        ['age' => PropertyExpression::gt(65)],
                    ]),
                    ['name' => 'Bill'],
                ]],
            ]))->toBe("SELECT * FROM `fixtures_simple` WHERE `enabled` = 1 AND (`age` < 18 OR `age` > 65) AND `name` = 'Bill'");
        });

        it('keeps implicit IN and explicit IN and NOT IN as value lists inside groups', function () {
            expect(Simple::buildQuery([
                Expression::or([
                    ['id' => [1, 2], 'name' => PropertyExpression::in(['Bill', 'John'])],
                    ['id' => PropertyExpression::notIn([3, 4]), 'enabled' => false],
                ]),
            ]))->toBe("SELECT * FROM `fixtures_simple` WHERE (`id` IN (1, 2) AND `name` IN ('Bill', 'John') OR `id` NOT IN (3, 4) AND `enabled` = 0)");
        });

        it('negates an entire nested conjunction', function () {
            expect(Simple::buildQuery([
                Expression::not(Expression::and([
                    ['id' => [1, 2]],
                    ['enabled' => true, ['name' => 'Bill']],
                ])),
            ]))->toBe("SELECT * FROM `fixtures_simple` WHERE NOT (`id` IN (1, 2) AND `enabled` = 1 AND `name` = 'Bill')");
        });

        it('preserves empty value-list predicates within nested groups', function () {
            expect(Simple::buildQuery([
                Expression::or([
                    ['id' => [], 'enabled' => true],
                    ['id' => PropertyExpression::notIn([]), 'enabled' => false],
                ]),
            ]))->toBe('SELECT * FROM `fixtures_simple` WHERE (1 = 0 AND `enabled` = 1 OR 1 = 1 AND `enabled` = 0)');
        });

        it('keeps arrays inside IN and NOT IN as JSON values rather than condition groups', function () {
            expect(Simple::buildQuery([
                ['payload' => [[1, 2], [3, 4]]],
                ['payload' => PropertyExpression::notIn([[5, 6]])],
            ]))->toBe("SELECT * FROM `fixtures_simple` WHERE `payload` IN ('[1,2]', '[3,4]') AND `payload` NOT IN ('[5,6]')");
        });
    });

    describe('Negation and empty sets', function () {
        it('negates the entire conjunction', function () {
            $expression = Expression::not(Expression::and(['a' => 1, 'b' => 2]));
            $expression->connection = $this->connection;
            expect((string) $expression)->toBe('NOT (`a` = 1 AND `b` = 2)');
        });

        it('preserves grouping for disjunctions and nested negations', function () {
            $expression = Expression::not(Expression::or(['a' => 1, 'b' => 2]));
            $expression->connection = $this->connection;
            expect((string) $expression)->toBe('NOT ((`a` = 1 OR `b` = 2))');
            $expression = Expression::not(Expression::not(Expression::buildForAttribute('a', 1)));
            $expression->connection = $this->connection;
            expect((string) $expression)->toBe('NOT (NOT (`a` = 1))');
        });

        it('renders empty IN as false and empty NOT IN as true', function () {
            foreach ([[[], '1 = 0'], [PropertyExpression::in([]), '1 = 0'], [PropertyExpression::notIn([]), '1 = 1']] as [$value, $sql]) {
                $expression = Expression::buildForAttribute('id', $value);
                $expression->connection = $this->connection;
                expect((string) $expression)->toBe($sql);
            }
        });

        it('preserves empty-set predicates in queries and negated groups', function () {
            allow(Simple::class)->toReceive('::getConnection')->andReturn($this->connection);
            expect(Simple::buildQuery(['id' => []]))->toBe('SELECT * FROM `fixtures_simple` WHERE 1 = 0');
            expect(Simple::buildQuery(['id' => PropertyExpression::notIn([])]))->toBe('SELECT * FROM `fixtures_simple` WHERE 1 = 1');
            expect(Simple::buildQuery([
                Expression::not(Expression::and(['id' => [], 'enabled' => true])),
                'name' => 'Bill',
            ]))->toBe("SELECT * FROM `fixtures_simple` WHERE NOT (1 = 0 AND `enabled` = 1) AND `name` = 'Bill'");
        });
    });

});
