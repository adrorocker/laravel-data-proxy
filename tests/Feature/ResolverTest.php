<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Tests\Feature;

use AdroSoftware\DataProxy\Config;
use AdroSoftware\DataProxy\DataSet;
use AdroSoftware\DataProxy\Requirements;
use AdroSoftware\DataProxy\Resolver;
use AdroSoftware\DataProxy\Result;
use AdroSoftware\DataProxy\Shape;
use AdroSoftware\DataProxy\Tests\Fixtures\Models\Post;
use AdroSoftware\DataProxy\Tests\Fixtures\Models\Profile;
use AdroSoftware\DataProxy\Tests\Fixtures\Models\User;
use AdroSoftware\DataProxy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function setUpDatabase(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->integer('age')->default(0);
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('posts', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->boolean('published')->default(false);
            $table->integer('views')->default(0);
            $table->timestamps();
        });

        $this->app['db']->connection()->getSchemaBuilder()->create('profiles', function ($table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('bio')->nullable();
            $table->string('avatar')->nullable();
            $table->timestamps();
        });
    }

    protected function createResolver(Requirements $requirements, array $configOverrides = []): Resolver
    {
        $config = new Config(array_merge([
            'cache' => ['enabled' => false],
            'metrics' => ['enabled' => true],
        ], $configOverrides));

        return new Resolver($requirements, $config);
    }

    // ========== Entity Resolution Tests ==========

    public function test_it_resolves_single_entity_by_id(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@example.com']);

        $requirements = Requirements::make()
            ->one('user', User::class, $user->id);

        $result = $this->createResolver($requirements)->resolve();

        $this->assertInstanceOf(Result::class, $result);
        $this->assertInstanceOf(User::class, $result->get('user'));
        $this->assertEquals('John', $result->get('user')->name);
    }

    public function test_it_resolves_single_entity_with_callable_id(): void
    {
        $user = User::create(['name' => 'Jane', 'email' => 'jane@example.com']);

        $requirements = Requirements::make()
            ->one('user', User::class, fn() => $user->id);

        $result = $this->createResolver($requirements)->resolve();

        $this->assertEquals('Jane', $result->get('user')->name);
    }

    public function test_it_returns_null_for_non_existent_entity(): void
    {
        $requirements = Requirements::make()
            ->one('user', User::class, 99999);

        $result = $this->createResolver($requirements)->resolve();

        $this->assertNull($result->get('user'));
    }

    public function test_it_resolves_many_entities_by_ids(): void
    {
        $user1 = User::create(['name' => 'User 1', 'email' => 'user1@example.com']);
        $user2 = User::create(['name' => 'User 2', 'email' => 'user2@example.com']);
        $user3 = User::create(['name' => 'User 3', 'email' => 'user3@example.com']);

        $requirements = Requirements::make()
            ->many('users', User::class, [$user1->id, $user3->id]);

        $result = $this->createResolver($requirements)->resolve();

        $this->assertInstanceOf(DataSet::class, $result->get('users'));
        $this->assertCount(2, $result->get('users'));
    }

    public function test_it_returns_empty_dataset_for_empty_ids(): void
    {
        $requirements = Requirements::make()
            ->many('users', User::class, []);

        $result = $this->createResolver($requirements)->resolve();

        $this->assertInstanceOf(DataSet::class, $result->get('users'));
        $this->assertTrue($result->get('users')->isEmpty());
    }

    public function test_it_batches_multiple_entity_lookups_for_same_model(): void
    {
        $user1 = User::create(['name' => 'User 1', 'email' => 'user1@example.com']);
        $user2 = User::create(['name' => 'User 2', 'email' => 'user2@example.com']);

        $requirements = Requirements::make()
            ->one('first', User::class, $user1->id)
            ->one('second', User::class, $user2->id);

        $result = $this->createResolver($requirements)->resolve();

        // Should use only 1 query due to batching
        $this->assertEquals(1, $result->metrics()['queries']);
        $this->assertEquals('User 1', $result->get('first')->name);
        $this->assertEquals('User 2', $result->get('second')->name);
    }

    public function test_it_selects_specific_fields(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@example.com', 'age' => 30]);

        $requirements = Requirements::make()
            ->one('user', User::class, $user->id, Shape::make()->select('id', 'name'));

        $result = $this->createResolver($requirements)->resolve();

        $this->assertEquals('John', $result->get('user')->name);
        // Email should not be selected (will be null or not present)
        $this->assertNull($result->get('user')->email ?? null);
    }

    public function test_it_returns_as_array_when_specified(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@example.com']);

        $requirements = Requirements::make()
            ->one('user', User::class, $user->id, Shape::make()->asArray());

        $result = $this->createResolver($requirements)->resolve();

        $this->assertIsArray($result->get('user'));
        $this->assertEquals('John', $result->get('user')['name']);
    }

    // ========== Relation Loading Tests ==========

    public function test_it_eager_loads_relations(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
        Profile::create(['user_id' => $user->id, 'bio' => 'Developer']);

        $requirements = Requirements::make()
            ->one('user', User::class, $user->id, Shape::make()->with('profile'));

        $result = $this->createResolver($requirements)->resolve();

        $this->assertTrue($result->get('user')->relationLoaded('profile'));
        $this->assertEquals('Developer', $result->get('user')->profile->bio);
    }

    public function test_it_eager_loads_nested_relations_with_shape(): void
    {
        $user = User::create(['name' => 'John', 'email' => 'john@example.com']);
        Post::create(['user_id' => $user->id, 'title' => 'First Post', 'body' => 'Content']);
        Post::create(['user_id' => $user->id, 'title' => 'Second Post', 'body' => 'More content']);

        $requirements = Requirements::make()
            ->one('user', User::class, $user->id, Shape::make()
                ->with('posts', Shape::make()
                    ->select('id', 'user_id', 'title')  // Include user_id for relation
                )
            );

        $result = $this->createResolver($requirements)->resolve();

        $this->assertTrue($result->get('user')->relationLoaded('posts'));
        $this->assertCount(2, $result->get('user')->posts);
    }

    // ========== Query Resolution Tests ==========

    public function test_it_resolves_query_with_constraints(): void
    {
        User::create(['name' => 'Young', 'email' => 'young@example.com', 'age' => 20]);
        User::create(['name' => 'Old', 'email' => 'old@example.com', 'age' => 60]);

        $requirements = Requirements::make()
            ->query('adults', User::class, Shape::make()
                ->where('age', '>=', 21)
            );

        $result = $this->createResolver($requirements)->resolve();

        $this->assertCount(1, $result->get('adults'));
        $this->assertEquals('Old', $result->get('adults')->first()->name);
    }

    public function test_it_resolves_query_with_where_in(): void
    {
        $user1 = User::create(['name' => 'User 1', 'email' => 'user1@example.com']);
        User::create(['name' => 'User 2', 'email' => 'user2@example.com']);
        $user3 = User::create(['name' => 'User 3', 'email' => 'user3@example.com']);

        $requirements = Requirements::make()
            ->query('selected', User::class, Shape::make()
                ->whereIn('id', [$user1->id, $user3->id])
            );

        $result = $this->createResolver($requirements)->resolve();

        $this->assertCount(2, $result->get('selected'));
    }

    public function test_it_resolves_query_with_where_not_in(): void
    {
        $user1 = User::create(['name' => 'User 1', 'email' => 'user1@example.com']);
        User::create(['name' => 'User 2', 'email' => 'user2@example.com']);
        User::create(['name' => 'User 3', 'email' => 'user3@example.com']);

        $requirements = Requirements::make()
            ->query('others', User::class, Shape::make()
                ->whereNotIn('id', [$user1->id])
            );

        $result = $this->createResolver($requirements)->resolve();

        $this->assertCount(2, $result->get('others'));
    }

    public function test_it_resolves_query_with_where_null(): void
    {
        $user = User::create(['name' => 'Author', 'email' => 'author@example.com']);
        Post::create(['user_id' => $user->id, 'title' => 'Empty', 'body' => null]);
        Post::create(['user_id' => $user->id, 'title' => 'Full', 'body' => 'Content']);

        $requirements = Requirements::make()
            ->query('empty', Post::class, Shape::make()->whereNull('body'));

        $result = $this->createResolver($requirements)->resolve();

        $this->assertCount(1, $result->get('empty'));
    }

    public function test_it_resolves_query_with_ordering(): void
    {
        User::create(['name' => 'Bravo', 'email' => 'bravo@example.com']);
        User::create(['name' => 'Alpha', 'email' => 'alpha@example.com']);
        User::create(['name' => 'Charlie', 'email' => 'charlie@example.com']);

        $requirements = Requirements::make()
            ->query('sorted', User::class, Shape::make()
                ->orderBy('name', 'asc')
            );

        $result = $this->createResolver($requirements)->resolve();

        $names = $result->get('sorted')->pluck('name')->all();
        $this->assertEquals(['Alpha', 'Bravo', 'Charlie'], $names);
    }

    public function test_it_resolves_query_with_limit_and_offset(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            User::create(['name' => "User $i", 'email' => "user$i@example.com"]);
        }

        $requirements = Requirements::make()
            ->query('page', User::class, Shape::make()
                ->orderBy('id')
                ->limit(3)
                ->offset(2)
            );

        $result = $this->createResolver($requirements)->resolve();

        $this->assertCount(3, $result->get('page'));
        $this->assertEquals('User 3', $result->get('page')->first()->name);
    }

    public function test_it_applies_custom_scope(): void
    {
        User::create(['name' => 'Active', 'email' => 'active@example.com', 'age' => 25]);
        User::create(['name' => 'Inactive', 'email' => 'inactive@example.com', 'age' => 0]);

        $requirements = Requirements::make()
            ->query('active', User::class, Shape::make()
                ->scope(fn($query) => $query->where('age', '>', 0))
            );

        $result = $this->createResolver($requirements)->resolve();

        $this->assertCount(1, $result->get('active'));
        $this->assertEquals('Active', $result->get('active')->first()->name);
    }

    // ========== Aggregate Tests ==========

    public function test_it_resolves_count_aggregate(): void
    {
        User::create(['name' => 'User 1', 'email' => 'user1@example.com']);
        User::create(['name' => 'User 2', 'email' => 'user2@example.com']);
        User::create(['name' => 'User 3', 'email' => 'user3@example.com']);

        $requirements = Requirements::make()
            ->count('total', User::class);

        $result = $this->createResolver($requirements)->resolve();

        $this->assertEquals(3, $result->get('total'));
    }

    public function test_it_resolves_sum_aggregate(): void
    {
        User::create(['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20]);
        User::create(['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 30]);
        User::create(['name' => 'User 3', 'email' => 'user3@example.com', 'age' => 50]);

        $requirements = Requirements::make()
            ->sum('totalAge', User::class, 'age');

        $result = $this->createResolver($requirements)->resolve();

        $this->assertEquals(100, $result->get('totalAge'));
    }

    public function test_it_resolves_avg_aggregate(): void
    {
        User::create(['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20]);
        User::create(['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 40]);

        $requirements = Requirements::make()
            ->avg('avgAge', User::class, 'age');

        $result = $this->createResolver($requirements)->resolve();

        $this->assertEquals(30, $result->get('avgAge'));
    }

    public function test_it_resolves_min_max_aggregates(): void
    {
        User::create(['name' => 'Young', 'email' => 'young@example.com', 'age' => 18]);
        User::create(['name' => 'Old', 'email' => 'old@example.com', 'age' => 80]);

        $requirements = Requirements::make()
            ->min('youngest', User::class, 'age')
            ->max('oldest', User::class, 'age');

        $result = $this->createResolver($requirements)->resolve();

        $this->assertEquals(18, $result->get('youngest'));
        $this->assertEquals(80, $result->get('oldest'));
    }

    public function test_it_resolves_aggregate_with_constraints(): void
    {
        User::create(['name' => 'Adult 1', 'email' => 'adult1@example.com', 'age' => 25]);
        User::create(['name' => 'Adult 2', 'email' => 'adult2@example.com', 'age' => 30]);
        User::create(['name' => 'Child', 'email' => 'child@example.com', 'age' => 10]);

        $requirements = Requirements::make()
            ->count('adults', User::class, Shape::make()->where('age', '>=', 18));

        $result = $this->createResolver($requirements)->resolve();

        $this->assertEquals(2, $result->get('adults'));
    }

    public function test_it_batches_aggregates_with_same_constraints(): void
    {
        User::create(['name' => 'User 1', 'email' => 'user1@example.com', 'age' => 20]);
        User::create(['name' => 'User 2', 'email' => 'user2@example.com', 'age' => 30]);

        $requirements = Requirements::make()
            ->count('total', User::class)
            ->sum('totalAge', User::class, 'age')
            ->avg('avgAge', User::class, 'age');

        $result = $this->createResolver($requirements)->resolve();

        // All aggregates should be in one query
        $this->assertEquals(1, $result->metrics()['queries']);
        $this->assertEquals(2, $result->get('total'));
        $this->assertEquals(50, $result->get('totalAge'));
        $this->assertEquals(25, $result->get('avgAge'));
    }

    public function test_it_handles_aggregates_on_empty_table(): void
    {
        $requirements = Requirements::make()
            ->count('total', User::class)
            ->sum('totalAge', User::class, 'age');

        $result = $this->createResolver($requirements)->resolve();

        $this->assertEquals(0, $result->get('total'));
        $this->assertEquals(0, $result->get('totalAge'));
    }

    // ========== Computed Values Tests ==========

    public function test_it_resolves_computed_values(): void
    {
        User::create(['name' => 'User 1', 'email' => 'user1@example.com']);
        User::create(['name' => 'User 2', 'email' => 'user2@example.com']);

        $requirements = Requirements::make()
            ->count('total', User::class)
            ->compute('doubled', fn($resolved) => $resolved['total'] * 2, ['total']);

        $result = $this->createResolver($requirements)->resolve();

        $this->assertEquals(4, $result->get('doubled'));
    }

    public function test_it_resolves_computed_with_dependencies_in_correct_order(): void
    {
        User::create(['name' => 'User', 'email' => 'user@example.com']);

        $requirements = Requirements::make()
            ->count('a', User::class)
            ->compute('b', fn($r) => $r['a'] + 10, ['a'])
            ->compute('c', fn($r) => $r['b'] * 2, ['b']);

        $result = $this->createResolver($requirements)->resolve();

        $this->assertEquals(1, $result->get('a'));
        $this->assertEquals(11, $result->get('b'));
        $this->assertEquals(22, $result->get('c'));
    }

    // ========== Raw Query Tests ==========

    public function test_it_resolves_raw_sql_queries(): void
    {
        User::create(['name' => 'User 1', 'email' => 'user1@example.com']);
        User::create(['name' => 'User 2', 'email' => 'user2@example.com']);

        $requirements = Requirements::make()
            ->raw('emails', 'SELECT email FROM users ORDER BY id');

        $result = $this->createResolver($requirements)->resolve();

        $this->assertInstanceOf(DataSet::class, $result->get('emails'));
        $this->assertCount(2, $result->get('emails'));
    }

    public function test_it_resolves_raw_sql_with_bindings(): void
    {
        User::create(['name' => 'Match', 'email' => 'match@example.com']);
        User::create(['name' => 'NoMatch', 'email' => 'nomatch@example.com']);

        $requirements = Requirements::make()
            ->raw('found', 'SELECT * FROM users WHERE name = ?', ['Match']);

        $result = $this->createResolver($requirements)->resolve();

        $this->assertCount(1, $result->get('found'));
    }

    // ========== Validation Tests ==========

    public function test_it_throws_on_invalid_model_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Model class does not exist');

        $requirements = Requirements::make()
            ->one('user', 'NonExistentModel', 1);

        $this->createResolver($requirements)->resolve();
    }

    public function test_it_throws_on_non_model_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Class is not an Eloquent Model');

        $requirements = Requirements::make()
            ->one('user', \stdClass::class, 1);

        $this->createResolver($requirements)->resolve();
    }

    public function test_it_throws_on_invalid_aggregate_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid aggregate type');

        // Create requirements with invalid aggregate type using reflection
        $requirements = Requirements::make();
        $reflection = new \ReflectionClass($requirements);
        $property = $reflection->getProperty('aggregates');
        $property->setAccessible(true);
        $property->setValue($requirements, [
            'bad' => [
                'type' => 'DROP TABLE',
                'model' => User::class,
                'column' => 'id',
                'shape' => Shape::make(),
            ],
        ]);

        $this->createResolver($requirements)->resolve();
    }

    public function test_it_validates_column_names_in_aggregates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid column name');

        // Create requirements with invalid column name using reflection
        $requirements = Requirements::make();
        $reflection = new \ReflectionClass($requirements);
        $property = $reflection->getProperty('aggregates');
        $property->setAccessible(true);
        $property->setValue($requirements, [
            'bad' => [
                'type' => 'sum',
                'model' => User::class,
                'column' => 'id; DROP TABLE users;--',
                'shape' => Shape::make(),
            ],
        ]);

        $this->createResolver($requirements)->resolve();
    }

    // ========== Metrics Tests ==========

    public function test_it_tracks_query_count(): void
    {
        User::create(['name' => 'User', 'email' => 'user@example.com']);

        $requirements = Requirements::make()
            ->one('user', User::class, 1)
            ->count('total', User::class);

        $result = $this->createResolver($requirements)->resolve();

        $this->assertArrayHasKey('queries', $result->metrics());
        $this->assertEquals(2, $result->metrics()['queries']);
    }

    public function test_it_tracks_batch_savings(): void
    {
        $user1 = User::create(['name' => 'User 1', 'email' => 'user1@example.com']);
        $user2 = User::create(['name' => 'User 2', 'email' => 'user2@example.com']);

        $requirements = Requirements::make()
            ->one('first', User::class, $user1->id)
            ->one('second', User::class, $user2->id);

        $result = $this->createResolver($requirements)->resolve();

        $this->assertEquals(1, $result->metrics()['batch_savings']);
    }
}
