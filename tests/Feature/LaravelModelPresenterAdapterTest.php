<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Tests\Feature;

use AdroSoftware\DataProxy\Adapters\LaravelModelPresenterAdapter;
use AdroSoftware\DataProxy\Tests\Fixtures\Models\User;
use AdroSoftware\DataProxy\Tests\TestCase;

// Test presenter class
class UserPresenter
{
    protected User $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    public function displayName(): string
    {
        return strtoupper($this->model->name);
    }

    public function getModel(): User
    {
        return $this->model;
    }
}

class LaravelModelPresenterAdapterTest extends TestCase
{
    protected LaravelModelPresenterAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
        $this->adapter = new LaravelModelPresenterAdapter();
    }

    protected function setUpDatabase(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function test_it_can_present_model_with_explicit_presenter(): void
    {
        $user = User::create(['name' => 'john', 'email' => 'john@example.com']);

        $presented = $this->adapter->present($user, UserPresenter::class);

        $this->assertInstanceOf(UserPresenter::class, $presented);
        $this->assertEquals('JOHN', $presented->displayName());
    }

    public function test_it_returns_model_when_no_presenter_found(): void
    {
        $user = User::create(['name' => 'john', 'email' => 'john@example.com']);

        // No presenter registered and auto-discover won't find one
        $presented = $this->adapter->present($user);

        $this->assertInstanceOf(User::class, $presented);
    }

    public function test_it_can_register_model_presenter_mapping(): void
    {
        $this->adapter->register(User::class, UserPresenter::class);

        $user = User::create(['name' => 'jane', 'email' => 'jane@example.com']);
        $presented = $this->adapter->present($user);

        $this->assertInstanceOf(UserPresenter::class, $presented);
        $this->assertEquals('JANE', $presented->displayName());
    }

    public function test_it_can_register_multiple_mappings(): void
    {
        $this->adapter->registerMany([
            User::class => UserPresenter::class,
        ]);

        $user = User::create(['name' => 'test', 'email' => 'test@example.com']);

        $this->assertTrue($this->adapter->hasPresenter($user));
    }

    public function test_has_presenter_returns_false_when_not_registered(): void
    {
        $user = User::create(['name' => 'test', 'email' => 'test@example.com']);

        $this->assertFalse($this->adapter->hasPresenter($user));
    }

    public function test_it_caches_auto_discovered_presenters(): void
    {
        $this->adapter->setNamespace('AdroSoftware\\DataProxy\\Tests\\Feature\\');
        $this->adapter->setSuffix('Presenter');

        $user = User::create(['name' => 'test', 'email' => 'test@example.com']);

        // First call triggers auto-discovery
        $presenter1 = $this->adapter->resolvePresenter($user);
        // Second call should use cached value
        $presenter2 = $this->adapter->resolvePresenter($user);

        $this->assertEquals($presenter1, $presenter2);
        $this->assertEquals(UserPresenter::class, $presenter1);
    }

    public function test_it_throws_on_invalid_presenter_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Presenter class does not exist');

        $user = User::create(['name' => 'test', 'email' => 'test@example.com']);

        $this->adapter->present($user, 'NonExistentPresenter');
    }

    public function test_it_can_set_custom_namespace(): void
    {
        $adapter = (new LaravelModelPresenterAdapter())
            ->setNamespace('Custom\\Namespace\\');

        $this->assertInstanceOf(LaravelModelPresenterAdapter::class, $adapter);
    }

    public function test_it_can_set_custom_suffix(): void
    {
        $adapter = (new LaravelModelPresenterAdapter())
            ->setSuffix('View');

        $this->assertInstanceOf(LaravelModelPresenterAdapter::class, $adapter);
    }

    public function test_register_returns_self_for_chaining(): void
    {
        $result = $this->adapter
            ->register(User::class, UserPresenter::class)
            ->setNamespace('App\\Presenters\\')
            ->setSuffix('Presenter');

        $this->assertInstanceOf(LaravelModelPresenterAdapter::class, $result);
    }
}
