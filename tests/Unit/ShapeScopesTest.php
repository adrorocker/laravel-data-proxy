<?php

declare(strict_types=1);

use AdroSoftware\DataProxy\Shape;

describe('Shape scopes', function () {
    it('accumulates multiple scopes', function () {
        $shape = Shape::make()
            ->scope(fn($q) => $q)
            ->scope(fn($q) => $q)
            ->scope(fn($q) => $q);

        expect($shape->getScopes())->toHaveCount(3);
    });

    it('returns null from getScope when no scopes defined', function () {
        $shape = Shape::make();

        expect($shape->getScope())->toBeNull();
        expect($shape->getScopes())->toBeEmpty();
    });

    it('returns merged callable from getScope', function () {
        $calls = [];

        $shape = Shape::make()
            ->scope(function ($q) use (&$calls) {
                $calls[] = 'first';
            })
            ->scope(function ($q) use (&$calls) {
                $calls[] = 'second';
            });

        $merged = $shape->getScope();
        expect($merged)->toBeCallable();

        // Execute the merged scope
        $merged(new stdClass(), []);

        expect($calls)->toBe(['first', 'second']);
    });

    it('applies scopes in order', function () {
        $order = [];

        $shape = Shape::make()
            ->scope(function ($q) use (&$order) {
                $order[] = 1;
            })
            ->scope(function ($q) use (&$order) {
                $order[] = 2;
            })
            ->scope(function ($q) use (&$order) {
                $order[] = 3;
            });

        $shape->getScope()(new stdClass(), []);

        expect($order)->toBe([1, 2, 3]);
    });

    it('passes resolved data to all scopes', function () {
        $received = [];

        $shape = Shape::make()
            ->scope(function ($q, $resolved) use (&$received) {
                $received[] = $resolved['key'];
            })
            ->scope(function ($q, $resolved) use (&$received) {
                $received[] = $resolved['key'];
            });

        $shape->getScope()(new stdClass(), ['key' => 'value']);

        expect($received)->toBe(['value', 'value']);
    });

    it('can clear all scopes', function () {
        $shape = Shape::make()
            ->scope(fn($q) => $q)
            ->scope(fn($q) => $q)
            ->clearScopes();

        expect($shape->getScopes())->toBeEmpty();
        expect($shape->getScope())->toBeNull();
    });

    it('maintains backwards compatibility with single scope', function () {
        $called = false;

        $shape = Shape::make()
            ->scope(function ($q) use (&$called) {
                $called = true;
            });

        $scope = $shape->getScope();
        expect($scope)->toBeCallable();

        $scope(new stdClass(), []);
        expect($called)->toBeTrue();
    });

    it('works with conditional when patterns', function () {
        $excludeIds = [1, 2, 3];

        $shape = Shape::make()
            ->scope(fn($q) => $q)
            ->when(! empty($excludeIds), fn($s) => $s->scope(fn($q) => $q));

        expect($shape->getScopes())->toHaveCount(2);
    });
});
