<?php

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Exception;

describe(Base::class, function () {
    beforeEach(function () {
        $this->model = new class extends Base {};
        $this->modelClass = $this->model::class;
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
    });

    describe('::findAll()', function () {
        it('it returns objects using built query', function () {
            allow($this->modelClass)->toReceive('::buildQuery')->andReturn('query');
            allow($this->modelClass)->toReceive('::findAllBySql')->with('query')->andReturn([1, 2, 3]);
            expect($this->modelClass::findAll())->toBe([1, 2, 3]);
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
    });

    describe('::findFirstByAttribute()', function () {
        it('it uses condition builder and ::findFirst() method', function () {
            allow($this->modelClass)->toReceive('::condition')->with('name', 'value')->andReturn('criteria');
            allow($this->modelClass)->toReceive('::findFirst')->with('criteria', 'order')->andReturn($this->model);
            expect($this->modelClass::findFirstByAttribute('name', 'value', 'order'))->toBe($this->model);
        });
    });

    describe('::findFirstByAttributes()', function () {
        it('it uses multiple condition builders and ::findFirst() method', function () {
            allow($this->modelClass)->toReceive('::condition')->with('name', 'value1')->andReturn('criteria1');
            allow($this->modelClass)->toReceive('::condition')->with('key', 'value2')->andReturn('criteria2');
            allow($this->modelClass)->toReceive('::findFirst')->with('criteria1 AND criteria2', 'order')->andReturn($this->model);
            expect($this->modelClass::findFirstByAttributes(['name' => 'value1', 'key' => 'value2'], 'order'))->toBe($this->model);
        });
    });
});
