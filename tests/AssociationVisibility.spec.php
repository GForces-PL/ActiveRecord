<?php

use Gforces\ActiveRecord\ActiveRecordException;
use Fixtures\{RelationChild, RelationVisibility, RelationVisibilityChild};
use Gforces\ActiveRecord\Association;

describe('Association property visibility', function () {
    // New owners load empty collections without requiring a database connection.
    foreach ([false, true] as $loaded) {
        $state = $loaded ? 'loaded' : 'uninitialized';

        foreach (['publicChildren', 'protectedChildren', 'privateChildren'] as $name) {
            it("allows the declaring class to read its $state $name", function () use ($loaded, $name) {
                $model = new RelationVisibility();
                $expected = $loaded ? [new RelationChild()] : [];
                if ($loaded) {
                    (new ReflectionProperty(RelationVisibility::class, $name))->setValue($model, $expected);
                }
                expect($model->readInDeclaringClass($name))->toBe($expected);
            });

            it("applies visibility to external reads of $state $name", function () use ($loaded, $name) {
                $model = new RelationVisibility();
                $property = new ReflectionProperty(RelationVisibility::class, $name);
                $expected = $loaded ? [new RelationChild()] : [];
                if ($loaded) {
                    $property->setValue($model, $expected);
                }
                if ($name === 'publicChildren') {
                    expect($model->$name)->toBe($expected);
                } else {
                    expect(fn() => $model->$name)->toThrow(new ActiveRecordException());
                    // A rejected read must not load or replace the relation.
                    expect($property->isInitialized($model))->toBe($loaded);
                    if ($loaded) {
                        expect($property->getValue($model))->toBe($expected);
                    }
                }
            });

            it("applies visibility to subclass reads of $state $name", function () use ($loaded, $name) {
                $model = new RelationVisibilityChild();
                $property = new ReflectionProperty(RelationVisibility::class, $name);
                $expected = $loaded ? [new RelationChild()] : [];
                if ($loaded) {
                    $property->setValue($model, $expected);
                }
                if ($name === 'privateChildren') {
                    expect(fn() => $model->readInChildClass($name))->toThrow(new ActiveRecordException());
                } else {
                    expect($model->readInChildClass($name))->toBe($expected);
                }
            });
        }
    }

    it('allows the declaring parent to lazy-load its private relation on a child instance', function () {
        $model = new RelationVisibilityChild();
        expect($model->readInDeclaringClass('privateChildren'))->toBe([]);
    });

    it('preserves hydrated relations through the constructor', function () {
        $model = (new ReflectionMethod(\Gforces\ActiveRecord\Base::class, 'createObject'))
            ->invoke(null, new ReflectionClass(RelationVisibility::class), (object) [
                'publicChildren' => '[1]',
                'protectedChildren' => '[2]',
                'privateChildren' => '[3]',
            ]);
        foreach (['publicChildren' => [1], 'protectedChildren' => [2], 'privateChildren' => [3]] as $name => $expected) {
            expect($model->readInDeclaringClass($name))->toBe($expected);
        }
    });

    it('preserves relations assigned before the parent constructor and after hydration', function () {
        $class = new ReflectionClass(new class extends RelationVisibilityChild {
            public function __construct()
            {
                foreach (['publicChildren', 'protectedChildren', 'privateChildren'] as $name) {
                    (new ReflectionProperty(RelationVisibility::class, $name))->setValue($this, []);
                }
                parent::__construct();
            }
        });
        $model = (new ReflectionMethod(\Gforces\ActiveRecord\Base::class, 'createObject'))
            ->invoke(null, $class, (object) []);
        foreach (['publicChildren', 'protectedChildren', 'privateChildren'] as $name) {
            expect((new ReflectionProperty(RelationVisibility::class, $name))->isInitialized($model))->toBe(true);
            expect($model->readInDeclaringClass($name))->toBe([]);
        }
    });

    it('preserves an explicitly initialized null relation', function () {
        $model = new class extends \Gforces\ActiveRecord\Base {
            #[\Gforces\ActiveRecord\Associations\HasOne(foreignKey: 'owner_id')]
            public ?RelationChild $child = null;
        };
        expect((new ReflectionProperty($model, 'child'))->isInitialized($model))->toBe(true);
        expect($model->child)->toBe(null);
    });

    it('keeps non-public relations available as internal association metadata', function () {
        $names = array_map(fn($association) => $association->property->getName(), Association::getAll(RelationVisibility::class));
        sort($names);
        expect($names)->toBe(['privateChildren', 'protectedChildren', 'publicChildren']);
    });
});
