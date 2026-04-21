<?php
namespace DPUWoo\Tests\ValueObjects;

use DPUWoo\Tests\TestCase;

class Price_Context_Test extends TestCase
{
    public function test_has_sale_price_returns_true_when_sale_exists(): void
    {
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 100.0,
            old_sale: 80.0,
            usd_baseline: 0.0,
            exchange_rate: \Exchange_Rate::first_run(1000.0),
            settings: [],
            category_ids: []
        );

        $this->assertTrue($context->has_sale_price());
    }

    public function test_has_sale_price_returns_false_when_no_sale(): void
    {
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 100.0,
            old_sale: 0.0,
            usd_baseline: 0.0,
            exchange_rate: \Exchange_Rate::first_run(1000.0),
            settings: [],
            category_ids: []
        );

        $this->assertFalse($context->has_sale_price());
    }

    public function test_get_setting_returns_value_when_exists(): void
    {
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 100.0,
            old_sale: 0.0,
            usd_baseline: 0.0,
            exchange_rate: \Exchange_Rate::first_run(1000.0),
            settings: ['margin' => 10, 'threshold' => 5],
            category_ids: []
        );

        $this->assertEquals(10, $context->get_setting('margin'));
        $this->assertEquals(5, $context->get_setting('threshold'));
    }

    public function test_get_setting_returns_default_when_not_found(): void
    {
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 100.0,
            old_sale: 0.0,
            usd_baseline: 0.0,
            exchange_rate: \Exchange_Rate::first_run(1000.0),
            settings: ['margin' => 10],
            category_ids: []
        );

        $this->assertEquals('default', $context->get_setting('nonexistent', 'default'));
        $this->assertNull($context->get_setting('nonexistent'));
    }

    public function test_context_stores_all_values(): void
    {
        $exchangeRate = new \Exchange_Rate(1250.0, 1000.0);
        
        $context = new \Price_Context(
            product_id: 42,
            old_regular: 50000.0,
            old_sale: 45000.0,
            usd_baseline: 50.0,
            exchange_rate: $exchangeRate,
            settings: ['margin' => 5],
            category_ids: [10, 20, 30]
        );

        $this->assertEquals(42, $context->product_id);
        $this->assertEquals(50000.0, $context->old_regular);
        $this->assertEquals(45000.0, $context->old_sale);
        $this->assertEquals(50.0, $context->usd_baseline);
        $this->assertEquals([10, 20, 30], $context->category_ids);
        $this->assertEquals(1.25, $context->exchange_rate->ratio);
    }

    public function test_exchange_rate_in_context(): void
    {
        $exchangeRate = new \Exchange_Rate(1500.0, 1000.0);
        
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 100.0,
            old_sale: 0.0,
            usd_baseline: 40.0,
            exchange_rate: $exchangeRate,
            settings: [],
            category_ids: []
        );

        $this->assertEquals(1.5, $context->exchange_rate->ratio);
        $this->assertEquals(50.0, $context->exchange_rate->percentage_change);
    }

    public function test_empty_category_ids(): void
    {
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 100.0,
            old_sale: 0.0,
            usd_baseline: 0.0,
            exchange_rate: \Exchange_Rate::first_run(1000.0),
            settings: [],
            category_ids: []
        );

        $this->assertEquals([], $context->category_ids);
    }
}