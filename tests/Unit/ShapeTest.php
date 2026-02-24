<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Tests\Unit;

use AdroSoftware\DataProxy\Shape;
use PHPUnit\Framework\TestCase;

class ShapeTest extends TestCase
{
    public function test_it_can_be_created(): void
    {
        $shape = Shape::make();
        
        $this->assertInstanceOf(Shape::class, $shape);
    }

    public function test_it_defaults_to_all_fields(): void
    {
        $shape = Shape::make();
        
        $this->assertEquals(['*'], $shape->getFields());
    }

    public function test_it_can_select_specific_fields(): void
    {
        $shape = Shape::make()->select('id', 'name', 'email');
        
        $this->assertEquals(['id', 'name', 'email'], $shape->getFields());
    }

    public function test_it_can_add_relations(): void
    {
        $shape = Shape::make()
            ->with('profile')
            ->with('posts');
        
        $relations = $shape->getRelations();
        
        $this->assertArrayHasKey('profile', $relations);
        $this->assertArrayHasKey('posts', $relations);
    }

    public function test_it_can_add_nested_relation_shapes(): void
    {
        $shape = Shape::make()
            ->with('posts', Shape::make()
                ->select('id', 'title')
                ->limit(5)
            );
        
        $relations = $shape->getRelations();
        
        $this->assertInstanceOf(Shape::class, $relations['posts']);
        $this->assertEquals(['id', 'title'], $relations['posts']->getFields());
        $this->assertEquals(5, $relations['posts']->getLimit());
    }

    public function test_it_can_add_where_constraints(): void
    {
        $shape = Shape::make()
            ->where('status', 'published')
            ->where('views', '>', 100);
        
        $constraints = $shape->getConstraints();
        
        $this->assertCount(2, $constraints);
        $this->assertEquals('basic', $constraints[0]['type']);
        $this->assertEquals('status', $constraints[0]['column']);
        $this->assertEquals('=', $constraints[0]['operator']);
        $this->assertEquals('published', $constraints[0]['value']);
    }

    public function test_it_can_add_where_in_constraints(): void
    {
        $shape = Shape::make()->whereIn('category_id', [1, 2, 3]);
        
        $constraints = $shape->getConstraints();
        
        $this->assertEquals('in', $constraints[0]['type']);
        $this->assertEquals([1, 2, 3], $constraints[0]['values']);
    }

    public function test_it_can_set_ordering(): void
    {
        $shape = Shape::make()
            ->orderBy('created_at', 'desc')
            ->orderBy('title', 'asc');
        
        $orderBy = $shape->getOrderBy();
        
        $this->assertCount(2, $orderBy);
        $this->assertEquals(['created_at', 'desc'], $orderBy[0]);
        $this->assertEquals(['title', 'asc'], $orderBy[1]);
    }

    public function test_latest_is_shorthand_for_order_by_desc(): void
    {
        $shape = Shape::make()->latest('published_at');
        
        $orderBy = $shape->getOrderBy();
        
        $this->assertEquals(['published_at', 'desc'], $orderBy[0]);
    }

    public function test_it_can_set_limit_and_offset(): void
    {
        $shape = Shape::make()->limit(10)->offset(20);
        
        $this->assertEquals(10, $shape->getLimit());
        $this->assertEquals(20, $shape->getOffset());
    }

    public function test_take_and_skip_are_aliases(): void
    {
        $shape = Shape::make()->take(10)->skip(20);
        
        $this->assertEquals(10, $shape->getLimit());
        $this->assertEquals(20, $shape->getOffset());
    }

    public function test_it_can_set_presenter(): void
    {
        $shape = Shape::make()->present('App\\Presenters\\UserPresenter');
        
        $this->assertEquals('App\\Presenters\\UserPresenter', $shape->getPresenter());
    }

    public function test_it_can_return_as_array(): void
    {
        $shape = Shape::make()->asArray();
        
        $this->assertTrue($shape->shouldReturnArray());
    }

    public function test_when_applies_callback_conditionally(): void
    {
        $shapeWithCondition = Shape::make()->when(true, fn($s) => $s->limit(10));
        $shapeWithoutCondition = Shape::make()->when(false, fn($s) => $s->limit(10));
        
        $this->assertEquals(10, $shapeWithCondition->getLimit());
        $this->assertNull($shapeWithoutCondition->getLimit());
    }
}
