<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Tests\Integration;

use AdroSoftware\DataProxy\Adapters\LaravelModelPresenterAdapter;
use AdroSoftware\DataProxy\Tests\TestCase;
use AdroSoftware\LaravelModelPresenter\Presenter\Model\ModelPresentable;
use AdroSoftware\LaravelModelPresenter\Presenter\Model\ModelPresenter;
use AdroSoftware\LaravelModelPresenter\Presenter\Model\ModelPresenterInterface;
use AdroSoftware\LaravelModelPresenter\Presenter\Model\PresentModel;
use Illuminate\Database\Eloquent\Model;

/**
 * Presenter extending the actual ModelPresenter from adrosoftware/laravel-model-presenter
 */
class TestUserPresenter extends ModelPresenter
{
    public function fullName(): string
    {
        return strtoupper($this->model->name);
    }

    public function formattedEmail(): string
    {
        return '[' . $this->model->email . ']';
    }
}

/**
 * Model using the actual PresentModel trait from adrosoftware/laravel-model-presenter
 */
class TestPresentableUser extends Model implements ModelPresentable
{
    use PresentModel;

    protected $table = 'test_users';
    protected $guarded = [];
    public $timestamps = false;

    protected string $presenter = TestUserPresenter::class;
}

/**
 * Model without the trait for comparison
 */
class TestRegularUser extends Model
{
    protected $table = 'test_users';
    protected $guarded = [];
    public $timestamps = false;
}

/**
 * Integration tests using the actual adrosoftware/laravel-model-presenter package
 */
class LaravelModelPresenterIntegrationTest extends TestCase
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
        $this->app['db']->connection()->getSchemaBuilder()->create('test_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email');
        });
    }

    public function test_adapter_delegates_to_model_present_method_when_trait_is_used(): void
    {
        $user = TestPresentableUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        $presented = $this->adapter->present($user);

        $this->assertInstanceOf(ModelPresenterInterface::class, $presented);
        $this->assertInstanceOf(TestUserPresenter::class, $presented);
        $this->assertEquals('JOHN DOE', $presented->fullName());
        $this->assertEquals('[john@example.com]', $presented->formattedEmail());
    }

    public function test_presenter_provides_access_to_underlying_model(): void
    {
        $user = TestPresentableUser::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

        $presented = $this->adapter->present($user);

        $this->assertInstanceOf(TestUserPresenter::class, $presented);
        $this->assertSame($user, $presented->getModel());
    }

    public function test_presenter_magic_getter_accesses_model_properties(): void
    {
        $user = TestPresentableUser::create(['name' => 'Test User', 'email' => 'test@example.com']);

        $presented = $this->adapter->present($user);

        // ModelPresenter's __get proxies to model
        $this->assertEquals('Test User', $presented->name);
        $this->assertEquals('test@example.com', $presented->email);
    }

    public function test_model_without_trait_uses_manual_instantiation(): void
    {
        $this->adapter->register(TestRegularUser::class, TestUserPresenter::class);

        $user = TestRegularUser::create(['name' => 'Regular User', 'email' => 'regular@example.com']);
        $presented = $this->adapter->present($user);

        $this->assertInstanceOf(TestUserPresenter::class, $presented);
        $this->assertEquals('REGULAR USER', $presented->fullName());
    }

    public function test_model_without_trait_and_no_registration_returns_model(): void
    {
        $user = TestRegularUser::create(['name' => 'No Presenter', 'email' => 'none@example.com']);

        $presented = $this->adapter->present($user);

        // No presenter registered and no trait, returns the model itself
        $this->assertInstanceOf(TestRegularUser::class, $presented);
        $this->assertSame($user, $presented);
    }

    public function test_presenter_can_be_overridden_for_trait_model(): void
    {
        // Even though the model uses the trait with a defined presenter,
        // we can force a different presenter class
        $user = TestPresentableUser::create(['name' => 'Override Test', 'email' => 'override@example.com']);

        // The adapter checks if explicit presenterClass is provided
        $presented = $this->adapter->present($user, TestUserPresenter::class);

        $this->assertInstanceOf(TestUserPresenter::class, $presented);
    }

    public function test_model_with_trait_has_presenter(): void
    {
        $user = TestPresentableUser::create(['name' => 'Has Presenter', 'email' => 'has@example.com']);

        // The adapter detects models that use the PresentModel trait
        $this->assertTrue($this->adapter->hasPresenter($user));
    }

    public function test_model_without_trait_or_registration_has_no_presenter(): void
    {
        $user = TestRegularUser::create(['name' => 'No Presenter', 'email' => 'no@example.com']);

        $this->assertFalse($this->adapter->hasPresenter($user));

        // But if we register it explicitly
        $this->adapter->register(TestRegularUser::class, TestUserPresenter::class);
        $this->assertTrue($this->adapter->hasPresenter($user));
    }

    public function test_presenter_instance_is_cached_on_model(): void
    {
        $user = TestPresentableUser::create(['name' => 'Cached', 'email' => 'cached@example.com']);

        // First call creates the presenter instance
        $presented1 = $user->present();
        // Second call returns the same cached instance
        $presented2 = $user->present();

        $this->assertSame($presented1, $presented2);
    }

    public function test_presenter_supports_array_access(): void
    {
        $user = TestPresentableUser::create(['name' => 'Array Access', 'email' => 'array@example.com']);

        $presented = $this->adapter->present($user);

        // ModelPresenter implements ArrayAccess
        $this->assertTrue(isset($presented['name']));
        $this->assertEquals('Array Access', $presented['name']);
    }

    public function test_presenter_to_array_returns_model_array(): void
    {
        $user = TestPresentableUser::create(['name' => 'To Array', 'email' => 'toarray@example.com']);

        $presented = $this->adapter->present($user);
        $array = $presented->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('To Array', $array['name']);
        $this->assertEquals('toarray@example.com', $array['email']);
    }
}
