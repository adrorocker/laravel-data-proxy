<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Tests\Unit;

use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Shape;
use PHPUnit\Framework\TestCase;

class RequirementsTest extends TestCase
{
    public function test_it_can_be_created(): void
    {
        $requirements = Requirements::make();

        $this->assertInstanceOf(Requirements::class, $requirements);
    }

    public function test_it_can_add_single_entity_requirement(): void
    {
        $requirements = Requirements::make()
            ->one('user', 'App\\Models\\User', 1);

        $entities = $requirements->getEntities();

        $this->assertArrayHasKey('user', $entities);
        $this->assertEquals('one', $entities['user']['type']);
        $this->assertEquals('App\\Models\\User', $entities['user']['model']);
        $this->assertEquals(1, $entities['user']['id']);
    }

    public function test_it_can_add_many_entities_requirement(): void
    {
        $requirements = Requirements::make()
            ->many('users', 'App\\Models\\User', [1, 2, 3]);

        $entities = $requirements->getEntities();

        $this->assertArrayHasKey('users', $entities);
        $this->assertEquals('many', $entities['users']['type']);
        $this->assertEquals([1, 2, 3], $entities['users']['ids']);
    }

    public function test_it_can_add_query_requirement(): void
    {
        $requirements = Requirements::make()
            ->query('posts', 'App\\Models\\Post', Shape::make()->limit(10));

        $queries = $requirements->getQueries();

        $this->assertArrayHasKey('posts', $queries);
        $this->assertEquals('App\\Models\\Post', $queries['posts']['model']);
        $this->assertEquals(10, $queries['posts']['shape']->getLimit());
    }

    public function test_it_can_add_paginate_requirement(): void
    {
        $requirements = Requirements::make()
            ->paginate('posts', 'App\\Models\\Post', perPage: 15, page: 2);

        $queries = $requirements->getQueries();

        $this->assertTrue($queries['posts']['paginate']);
        $this->assertEquals(15, $queries['posts']['perPage']);
        $this->assertEquals(2, $queries['posts']['page']);
    }

    public function test_it_can_add_count_aggregate(): void
    {
        $requirements = Requirements::make()
            ->count('totalPosts', 'App\\Models\\Post');

        $aggregates = $requirements->getAggregates();

        $this->assertEquals('count', $aggregates['totalPosts']['type']);
        $this->assertEquals('*', $aggregates['totalPosts']['column']);
    }

    public function test_it_can_add_sum_aggregate(): void
    {
        $requirements = Requirements::make()
            ->sum('totalViews', 'App\\Models\\Post', 'views');

        $aggregates = $requirements->getAggregates();

        $this->assertEquals('sum', $aggregates['totalViews']['type']);
        $this->assertEquals('views', $aggregates['totalViews']['column']);
    }

    public function test_it_can_add_raw_sql(): void
    {
        $requirements = Requirements::make()
            ->raw('trending', 'SELECT * FROM posts WHERE views > ?', [100]);

        $raw = $requirements->getRaw();

        $this->assertEquals('SELECT * FROM posts WHERE views > ?', $raw['trending']['sql']);
        $this->assertEquals([100], $raw['trending']['bindings']);
    }

    public function test_it_can_add_computed_value(): void
    {
        $computer = fn($data) => $data['total'] * 2;

        $requirements = Requirements::make()
            ->compute('doubled', $computer, ['total']);

        $computed = $requirements->getComputed();

        $this->assertSame($computer, $computed['doubled']['computer']);
        $this->assertEquals(['total'], $computed['doubled']['depends']);
    }

    public function test_it_can_add_cache_configuration(): void
    {
        $requirements = Requirements::make()
            ->one('user', 'App\\Models\\User', 1)
            ->cache('user', 'user:1', ttl: 3600, tags: ['users']);

        $cache = $requirements->getCache();

        $this->assertEquals('user:1', $cache['user']['key']);
        $this->assertEquals(3600, $cache['user']['ttl']);
        $this->assertEquals(['users'], $cache['user']['tags']);
    }

    public function test_all_returns_complete_requirements(): void
    {
        $requirements = Requirements::make()
            ->one('user', 'App\\Models\\User', 1)
            ->query('posts', 'App\\Models\\Post')
            ->count('total', 'App\\Models\\Post')
            ->compute('stats', fn($d) => [])
            ->raw('custom', 'SELECT 1');

        $all = $requirements->all();

        $this->assertArrayHasKey('entities', $all);
        $this->assertArrayHasKey('queries', $all);
        $this->assertArrayHasKey('aggregates', $all);
        $this->assertArrayHasKey('computed', $all);
        $this->assertArrayHasKey('raw', $all);
    }
}
