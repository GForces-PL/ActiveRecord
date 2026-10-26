<?php

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
                    expect(fn() => $model->$name)->toThrow(new Error());
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
                    expect(fn() => $model->readInChildClass($name))->toThrow(new Error());
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

    it('unsets every relation after hydration even when a custom constructor initializes it', function () {
        $class = new ReflectionClass(new class extends RelationVisibilityChild {
            public function __construct()
            {
                foreach (['publicChildren', 'protectedChildren', 'privateChildren'] as $name) {
                    (new ReflectionProperty(RelationVisibility::class, $name))->setValue($this, []);
                }
            }
        });
        $model = (new ReflectionMethod(\Gforces\ActiveRecord\Base::class, 'createObject'))
            ->invoke(null, $class, (object) ['publicChildren' => '[]', 'protectedChildren' => '[]']);
        foreach (['publicChildren', 'protectedChildren', 'privateChildren'] as $name) {
            expect((new ReflectionProperty(RelationVisibility::class, $name))->isInitialized($model))->toBe(false);
        }
    });

    it('keeps non-public relations available as internal association metadata', function () {
        $names = array_map(fn($association) => $association->property->getName(), Association::getAll(RelationVisibility::class));
        sort($names);
        expect($names)->toBe(['privateChildren', 'protectedChildren', 'publicChildren']);
    });
});
