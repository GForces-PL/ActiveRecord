<?php

use Gforces\ActiveRecord\Base;
use Gforces\ActiveRecord\Connection;
use Gforces\ActiveRecord\ValidationContext;
use Gforces\ActiveRecord\ValidationException;
use Gforces\ActiveRecord\Validators\{Required, Length, ByteLength, Matches, Url};
use Kahlan\Plugin\Double;

describe('Validation lifecycle', function () {
    given('model', fn() => new class extends Base {
        #[Required(message: 'Name is required')]
        #[Length(min: 3, message: 'Name is too short')]
        public string $name;
    });

    it('replaces previous errors without duplicating messages', function () {
        $this->model->name = '';
        expect($this->model->getErrors(true))->toBe(['name' => ['Name is required']]);
        expect($this->model->getErrors(true))->toBe(['name' => ['Name is required']]);
        $this->model->name = 'ab';
        expect($this->model->getErrorMessages(true))->toBe(['Name is too short']);
        $this->model->name = 'Valid';
        expect($this->model->isValid())->toBe(true);
        expect($this->model->getErrors())->toBe([]);
        expect($this->model->getErrorMessages())->toBe([]);
    });

    it('retains the last result until validation is explicitly requested', function () {
        $this->model->name = '';
        $this->model->isValid();
        $this->model->name = 'Valid';
        expect($this->model->getErrors())->toBe(['name' => ['Name is required']]);
        expect($this->model->getErrorMessages())->toBe(['Name is required']);
        expect($this->model->getErrors(true))->toBe([]);
    });

    it('reports required errors for uninitialized new fields without crashing other validators', function () {
        expect($this->model->getErrors(true))->toBe(['name' => ['Name is required']]);
    });

    it('preserves Required semantics for fields omitted from existing objects', function () {
        $this->model->isNew = false;
        expect($this->model->isValid())->toBe(true);
        $this->model->name = '';
        expect($this->model->getErrors(true))->toBe(['name' => ['Name is required']]);
    });

    it('revalidates corrected data when retrying save', function () {
        $model = $this->model;
        $class = $model::class;
        $connection = Double::instance(['extends' => Connection::class, 'methods' => ['__construct'], 'layer' => true]);
        $class::setConnection($connection);
        allow($class)->toReceive('::insert')->andReturn(1);
        expect($class)->toReceive('::insert')->once();
        $model->name = '';
        expect(fn() => $model->save())->toThrow(new ValidationException());
        expect($model->isNew)->toBe(true);
        $model->name = 'Valid';
        $model->save();
        expect($model->isNew)->toBe(false);
        expect($model->getErrors())->toBe([]);
    });

    it('clears errors when a validation context no longer applies', function () {
        $model = new class extends Base {
            #[Required(context: ValidationContext::onCreate)] public string $create = '';
            #[Required(context: ValidationContext::onUpdate)] public string $update = '';
        };
        expect($model->getErrors(true))->toBe(['create' => ['create is required']]);
        $model->isNew = false;
        expect($model->getErrors(true))->toBe(['update' => ['update is required']]);
    });

    foreach ([Length::class, ByteLength::class, Matches::class, Url::class] as $validatorClass) {
        it("skips absent optional values but validates present ones ($validatorClass)", function () use ($validatorClass) {
            $model = new class extends Base { public ?string $value; };
            $validator = match ($validatorClass) {
                Length::class => new Length(min: 20),
                ByteLength::class => new ByteLength(min: 20),
                Matches::class => new Matches('/^valid$/'),
                Url::class => new Url(),
            };
            $validator->property = new ReflectionProperty($model, 'value');
            foreach ([true, false] as $isNew) {
                $model->isNew = $isNew;
                unset($model->value);
                expect(fn() => $validator->perform($model))->not->toThrow();
                foreach ([null, ''] as $value) {
                    $model->value = $value;
                    expect(fn() => $validator->perform($model))->not->toThrow();
                }
                $model->value = 'bad';
                expect(fn() => $validator->perform($model))->toThrow(new ValidationException());
            }
        });
    }
});
