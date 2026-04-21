<?php
namespace DPUWoo\Tests\Rules;

use DPUWoo\Tests\TestCase;

class Ratio_Rule_Test extends TestCase
{
    public function test_apply_uses_usd_baseline_when_available(): void
    {
        $rule = new \Ratio_Rule();
        
        $exchangeRate = new \Exchange_Rate(1500.0, 1000.0);
        
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 50000.0,
            old_sale: 0.0,
            usd_baseline: 50.0,
            exchange_rate: $exchangeRate,
            settings: [],
            category_ids: []
        );
        
        $result = $rule->apply(50000.0, $context);
        
        $this->assertEquals(75.0, $result);
    }

    public function test_apply_falls_back_to_price_when_no_usd_baseline(): void
    {
        $rule = new \Ratio_Rule();
        
        $exchangeRate = new \Exchange_Rate(1500.0, 1000.0);
        
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 50000.0,
            old_sale: 0.0,
            usd_baseline: 0.0,
            exchange_rate: $exchangeRate,
            settings: [],
            category_ids: []
        );
        
        $result = $rule->apply(50000.0, $context);
        
        $this->assertEquals(75000.0, $result);
    }

    public function test_apply_ignores_usd_baseline_when_zero(): void
    {
        $rule = new \Ratio_Rule();
        
        $exchangeRate = new \Exchange_Rate(1500.0, 1000.0);
        
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 50000.0,
            old_sale: 0.0,
            usd_baseline: 0.0,
            exchange_rate: $exchangeRate,
            settings: [],
            category_ids: []
        );
        
        $result = $rule->apply(50000.0, $context);
        
        $this->assertEquals(75000.0, $result);
    }

    public function test_get_rule_key_formats_ratio_correctly(): void
    {
        $rule = new \Ratio_Rule();
        
        $exchangeRate = new \Exchange_Rate(1250.0, 1000.0);
        
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 100.0,
            old_sale: 0.0,
            usd_baseline: 0.0,
            exchange_rate: $exchangeRate,
            settings: [],
            category_ids: []
        );
        
        $rule->apply(100.0, $context);
        
        $this->assertEquals('ratio_1.25', $rule->get_rule_key());
    }

    public function test_apply_with_first_run_ratio(): void
    {
        $rule = new \Ratio_Rule();
        
        $exchangeRate = \Exchange_Rate::first_run(1250.0);
        
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 50000.0,
            old_sale: 0.0,
            usd_baseline: 0.0,
            exchange_rate: $exchangeRate,
            settings: [],
            category_ids: []
        );
        
        $result = $rule->apply(50000.0, $context);
        
        $this->assertEquals(50000.0, $result);
    }

    public function test_ratio_calculation_with_usd_baseline(): void
    {
        $rule = new \Ratio_Rule();
        
        $exchangeRate = new \Exchange_Rate(1250.0, 1000.0);
        
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 50000.0,
            old_sale: 0.0,
            usd_baseline: 40.0,
            exchange_rate: $exchangeRate,
            settings: [],
            category_ids: []
        );
        
        $result = $rule->apply(50000.0, $context);
        
        $this->assertEquals(50.0, $result);
    }

    public function test_ratio_calculation_without_usd_baseline(): void
    {
        $rule = new \Ratio_Rule();
        
        $exchangeRate = new \Exchange_Rate(1250.0, 1000.0);
        
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 50000.0,
            old_sale: 0.0,
            usd_baseline: 0.0,
            exchange_rate: $exchangeRate,
            settings: [],
            category_ids: []
        );
        
        $result = $rule->apply(50000.0, $context);
        
        $this->assertEquals(62500.0, $result);
    }
}