<?php

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Column;
use Gforces\ActiveRecord\JsonArray;
use Gforces\ActiveRecord\Connection;
use Gforces\ActiveRecord\ActiveRecordException;
use Kahlan\Plugin\Double;

describe('Hydration type safety', function () {
    beforeEach(function () {
        $this->connection = Double::instance(['extends' => Connection::class, 'methods' => ['__construct'], 'layer' => true]);
        $this->load = function (Base $prototype, array $row) {
            $prototype::setConnection($this->connection);
            $statement = Double::instance(['extends' => Connection\Statement::class, 'methods' => ['__construct'], 'layer' => true]);
            allow($statement)->toReceive('fetchObject')->andReturn((object) $row, false);
            allow($this->connection)->toReceive('query')->andReturn($statement);
            return $prototype::findAllBySql('SELECT test')[0];
        };
        $this->prototype = new class extends Base {
            #[Column] public ?Fixtures\HttpStatusCode $backed;
            #[Column] public ?Fixtures\State $unit;
            #[Column] public ?DateTime $date;
            #[Column] public ?Fixtures\MutableString $label;
            #[Column] public ?array $data;
            #[Column] public string $required;
        };
    });

    it('preserves SQL NULL for all nullable converted types', function () {
        $row = array_fill_keys(['backed', 'unit', 'date', 'label', 'data'], null);
        $model = ($this->load)($this->prototype, $row);
        foreach ($row as $key => $value) {
            expect($model->$key)->toBe(null);
        }
    });

    it('decodes nested objects and lists as arrays', function () {
        $model = ($this->load)($this->prototype, ['data' => '{"nested":{"value":1},"list":[{"ok":true}]}']);
        expect($model->data)->toBe(['nested' => ['value' => 1], 'list' => [['ok' => true]]]);
    });

    foreach (['[]', '{}'] as $json) {
        it("accepts an empty JSON container ($json)", function () use ($json) {
            expect(($this->load)($this->prototype, ['data' => $json])->data)->toBe([]);
        });
    }

    foreach (['broken', 'null', '42', 'true', '"text"'] as $json) {
        it("rejects invalid JSON or a non-container root ($json)", function () use ($json) {
            expect(fn() => ($this->load)($this->prototype, ['data' => $json]))->toThrow(new ActiveRecordException());
        });
    }

    it('reports the property for a non-nullable NULL', function () {
        try {
            ($this->load)($this->prototype, ['required' => null]);
            expect(false)->toBe(true);
        } catch (ActiveRecordException $e) {
            expect(str_contains($e->getMessage(), '::$required:'))->toBe(true);
            expect($e->getPrevious()->getPrevious())->toBeAnInstanceOf(TypeError::class);
        }
    });

    it('does not confuse empty custom strings with NULL', function () {
        $model = ($this->load)($this->prototype, ['label' => '']);
        expect($model->label)->toBeAnInstanceOf(Fixtures\MutableString::class);
        expect((string) $model->label)->toBe('');
    });

    it('rejects untyped, union and unsupported named types with a library exception', function () {
        $model = new class extends Base {
            #[Column] public $untyped;
            #[Column] public int|string $union;
            #[Column] public object $unsupported;
        };
        foreach (['untyped', 'union', 'unsupported'] as $key) {
            try {
                ($this->load)($model, [$key => 'value']);
                expect(false)->toBe(true);
            } catch (ActiveRecordException $e) {
                expect(str_contains($e->getMessage(), '::$' . $key . ':'))->toBe(true);
            }
        }
    });

    it('rejects empty date strings instead of turning them into NULL or the current time', function () {
        expect(fn() => ($this->load)($this->prototype, ['date' => '']))->toThrow(new ActiveRecordException());
    });

    it('keeps omitted properties uninitialized and ignores unknown result columns', function () {
        $model = ($this->load)($this->prototype, ['unknown' => 'value']);
        expect((new ReflectionProperty($model, 'data'))->isInitialized($model))->toBe(false);
    });

    it('supports explicit true and the attribute default on private helper properties', function () {
        $prototype = new class extends Base {
            #[JsonArray(associative: true)] private array $explicit;
            #[JsonArray] private array $implicit;
            public function values(): array { return [$this->explicit, $this->implicit]; }
        };
        $json = '{"nested":{"value":1}}';
        $model = ($this->load)($prototype, ['explicit' => $json, 'implicit' => $json]);
        expect($model->values())->toBe([['nested' => ['value' => 1]], ['nested' => ['value' => 1]]]);
        expect(Column::getValues($model))->toBe([]);
    });

    it('preserves nested objects and map keys while returning an array at the root', function () {
        $prototype = new class extends Base {
            #[Column] #[JsonArray(associative: false)] public array $map;
            #[JsonArray(associative: false)] private array $list;
            public function items(): array { return $this->list; }
        };
        $model = ($this->load)($prototype, [
            'map' => '{"en":{"nested":{"value":1},"list":[{"ok":true}]}}',
            'list' => '[{"value":2},3]',
        ]);
        expect(array_keys($model->map))->toBe(['en']);
        expect($model->map['en'])->toBeAnInstanceOf(stdClass::class);
        expect($model->map['en']->nested->value)->toBe(1);
        expect($model->map['en']->list[0]->ok)->toBe(true);
        expect($model->items()[0])->toBeAnInstanceOf(stdClass::class);
        expect($model->items()[0]->value)->toBe(2);
        expect($model->items()[1])->toBe(3);
    });

    it('preserves nullability and validates roots before casting in object mode', function () {
        $prototype = new class extends Base {
            #[JsonArray(associative: false)] public ?array $nullable;
            #[JsonArray(associative: false)] public array $required;
        };
        expect(($this->load)($prototype, ['nullable' => null])->nullable)->toBe(null);
        foreach (['[]', '{}'] as $json) {
            expect(($this->load)($prototype, ['required' => $json])->required)->toBe([]);
        }
        foreach ([null, 'null', 'false', '42', '"text"', 'broken'] as $value) {
            expect(fn() => ($this->load)($prototype, ['required' => $value]))->toThrow(new ActiveRecordException());
        }
    });

    it('rejects invalid JSON encoding before executing an insert', function () {
        expect($this->connection)->not->toReceive('exec');
        $this->prototype::setConnection($this->connection);
        allow($this->connection)->toReceive('quoteIdentifier')->andRun(fn($id) => "`$id`");
        expect(fn() => $this->prototype::insert(['data' => ["\xB1"]]))->toThrow(new JsonException('', JSON_ERROR_UTF8));
    });
});
