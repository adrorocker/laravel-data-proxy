<?php

declare(strict_types=1);

namespace AdroSoftware\DataProxy\Tests\Unit;

use AdroSoftware\DataProxy\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function test_it_can_be_created_with_defaults(): void
    {
        $config = new Config();

        $this->assertInstanceOf(Config::class, $config);
    }

    public function test_it_has_default_cache_settings(): void
    {
        $config = new Config();

        $this->assertTrue($config->get('cache.enabled'));
        $this->assertEquals('data_proxy:', $config->get('cache.prefix'));
        $this->assertEquals(3600, $config->get('cache.ttl'));
    }

    public function test_it_has_default_query_settings(): void
    {
        $config = new Config();

        $this->assertEquals(1000, $config->get('query.chunk_size'));
        $this->assertEquals(5, $config->get('query.max_eager_load_depth'));
    }

    public function test_it_has_default_memory_settings(): void
    {
        $config = new Config();

        $this->assertEquals(128, $config->get('memory.max_mb'));
        $this->assertEquals(0.8, $config->get('memory.gc_threshold'));
    }

    public function test_it_has_default_metrics_settings(): void
    {
        $config = new Config();

        $this->assertTrue($config->get('metrics.enabled'));
        $this->assertFalse($config->get('metrics.detailed'));
    }

    public function test_it_has_default_presenter_settings(): void
    {
        $config = new Config();

        $this->assertFalse($config->get('presenter.enabled'));
        $this->assertNull($config->get('presenter.adapter'));
        $this->assertEquals('App\\Presenters\\', $config->get('presenter.namespace'));
        $this->assertEquals('Presenter', $config->get('presenter.suffix'));
    }

    public function test_it_can_be_created_with_custom_values(): void
    {
        $config = new Config([
            'cache' => [
                'enabled' => false,
                'ttl' => 7200,
            ],
        ]);

        $this->assertFalse($config->get('cache.enabled'));
        $this->assertEquals(7200, $config->get('cache.ttl'));
        // Other defaults should still be present
        $this->assertEquals('data_proxy:', $config->get('cache.prefix'));
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $config = new Config();

        $this->assertNull($config->get('nonexistent.key'));
        $this->assertEquals('default', $config->get('nonexistent.key', 'default'));
    }

    public function test_get_with_dot_notation(): void
    {
        $config = new Config([
            'deep' => [
                'nested' => [
                    'value' => 'found',
                ],
            ],
        ]);

        $this->assertEquals('found', $config->get('deep.nested.value'));
    }

    public function test_set_with_dot_notation(): void
    {
        $config = new Config();

        $config->set('cache.ttl', 1800);

        $this->assertEquals(1800, $config->get('cache.ttl'));
    }

    public function test_set_creates_nested_structure(): void
    {
        $config = new Config();

        $config->set('new.nested.key', 'value');

        $this->assertEquals('value', $config->get('new.nested.key'));
    }

    public function test_set_returns_self_for_chaining(): void
    {
        $config = new Config();

        $result = $config
            ->set('cache.enabled', false)
            ->set('metrics.enabled', false);

        $this->assertInstanceOf(Config::class, $result);
        $this->assertFalse($config->get('cache.enabled'));
        $this->assertFalse($config->get('metrics.enabled'));
    }

    public function test_all_returns_full_config(): void
    {
        $config = new Config();

        $all = $config->all();

        $this->assertIsArray($all);
        $this->assertArrayHasKey('cache', $all);
        $this->assertArrayHasKey('query', $all);
        $this->assertArrayHasKey('memory', $all);
        $this->assertArrayHasKey('metrics', $all);
        $this->assertArrayHasKey('presenter', $all);
    }

    public function test_merge_combines_configs(): void
    {
        $config = new Config([
            'cache' => ['enabled' => true],
        ]);

        $config->merge([
            'cache' => ['ttl' => 9999],
            'metrics' => ['enabled' => false],
        ]);

        $this->assertTrue($config->get('cache.enabled'));
        $this->assertEquals(9999, $config->get('cache.ttl'));
        $this->assertFalse($config->get('metrics.enabled'));
    }

    public function test_merge_returns_self_for_chaining(): void
    {
        $config = new Config();

        $result = $config->merge(['cache' => ['enabled' => false]]);

        $this->assertInstanceOf(Config::class, $result);
    }

    public function test_get_returns_default_when_path_traverses_non_array(): void
    {
        $config = new Config([
            'scalar' => 'value',
        ]);

        $this->assertNull($config->get('scalar.nested.key'));
        $this->assertEquals('default', $config->get('scalar.nested.key', 'default'));
    }
}
