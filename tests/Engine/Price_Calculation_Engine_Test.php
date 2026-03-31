<?php
namespace DPUWoo\Tests\Engine;

use DPUWoo\Tests\TestCase;

class Price_Calculation_Engine_Test extends TestCase
{
    private function createContext(
        float $old_regular = 100.0,
        float $old_sale = 0.0,
        ?\Exchange_Rate $rate = null,
        array $settings = []
    ): \Price_Context {
        return new \Price_Context(
            product_id: 1,
            old_regular: $old_regular,
            old_sale: $old_sale,
            exchange_rate: $rate ?? \Exchange_Rate::first_run(1250.0),
            settings: array_merge([
                'margin' => 0,
                'update_direction' => 'bidirectional',
                'rounding_type' => 'integer',
                'nearest_to' => 1,
                'exclude_categories' => [],
            ], $settings),
            category_ids: []
        );
    }

    public function test_ratio_rule_applies_exchange_rate(): void
    {
        $engine = new \Price_Calculation_Engine([new \Ratio_Rule()]);
        $rate = new \Exchange_Rate(1250.0, 1000.0);
        $context = $this->createContext(100.0, 0.0, $rate);

        $result = $engine->calculate($context);

        $this->assertEqualsWithDelta(125.0, $result->new_regular, 0.01);
        $this->assertEqualsWithDelta(25.0, $result->percentage_change, 0.01);
        $this->assertContains('ratio_1.25', $result->applied_rules);
    }

    public function test_margin_rule_applies_percentage_adjustment(): void
    {
        $engine = new \Price_Calculation_Engine([
            new \Ratio_Rule(),
            new \Margin_Rule()
        ]);
        $rate = new \Exchange_Rate(1250.0, 1000.0);
        $context = $this->createContext(100.0, 0.0, $rate, ['margin' => 10]);

        $result = $engine->calculate($context);

        $this->assertEqualsWithDelta(137.5, $result->new_regular, 0.01);
    }

    public function test_direction_up_only_blocks_decrease(): void
    {
        $engine = new \Price_Calculation_Engine([
            new \Ratio_Rule(),
            new \Direction_Rule()
        ]);
        $rate = new \Exchange_Rate(900.0, 1000.0);
        $context = $this->createContext(100.0, 0.0, $rate, ['update_direction' => 'up_only']);

        $result = $engine->calculate($context);

        $this->assertEquals(100.0, $result->new_regular);
        $this->assertContains('direction_up_only_blocked', $result->applied_rules);
    }

    public function test_direction_up_only_allows_increase(): void
    {
        $engine = new \Price_Calculation_Engine([
            new \Ratio_Rule(),
            new \Direction_Rule()
        ]);
        $rate = new \Exchange_Rate(1100.0, 1000.0);
        $context = $this->createContext(100.0, 0.0, $rate, ['update_direction' => 'up_only']);

        $result = $engine->calculate($context);

        $this->assertEqualsWithDelta(110.0, $result->new_regular, 0.01);
        $this->assertContains('direction_up_only_allowed', $result->applied_rules);
    }

    public function test_direction_down_only_blocks_increase(): void
    {
        $engine = new \Price_Calculation_Engine([
            new \Ratio_Rule(),
            new \Direction_Rule()
        ]);
        $rate = new \Exchange_Rate(1100.0, 1000.0);
        $context = $this->createContext(100.0, 0.0, $rate, ['update_direction' => 'down_only']);

        $result = $engine->calculate($context);

        $this->assertEquals(100.0, $result->new_regular);
        $this->assertContains('direction_down_only_blocked', $result->applied_rules);
    }

    public function test_category_exclusion_reverts_to_original(): void
    {
        $engine = new \Price_Calculation_Engine([
            new \Ratio_Rule(),
            new \Category_Exclusion_Rule()
        ]);
        $rate = new \Exchange_Rate(1250.0, 1000.0);
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 100.0,
            old_sale: 0.0,
            exchange_rate: $rate,
            settings: ['exclude_categories' => [10]],
            category_ids: [10, 20]
        );

        $result = $engine->calculate($context);

        $this->assertEquals(100.0, $result->new_regular);
        $this->assertContains('category_exclusion', $result->applied_rules);
    }

    public function test_category_not_excluded_processes_normally(): void
    {
        $engine = new \Price_Calculation_Engine([
            new \Ratio_Rule(),
            new \Category_Exclusion_Rule()
        ]);
        $rate = new \Exchange_Rate(1250.0, 1000.0);
        $context = new \Price_Context(
            product_id: 1,
            old_regular: 100.0,
            old_sale: 0.0,
            exchange_rate: $rate,
            settings: ['exclude_categories' => [10]],
            category_ids: [20, 30]
        );

        $result = $engine->calculate($context);

        $this->assertEqualsWithDelta(125.0, $result->new_regular, 0.01);
    }

    public function test_rounding_ceil_always_rounds_up(): void
    {
        $engine = new \Price_Calculation_Engine([
            new \Ratio_Rule(),
            new \Rounding_Rule()
        ]);
        $rate = new \Exchange_Rate(1250.0, 1000.0);
        $context = $this->createContext(101.5, 0.0, $rate, ['rounding_type' => 'ceil']);

        $result = $engine->calculate($context);

        $this->assertEquals(127.0, $result->new_regular);
    }

    public function test_rounding_floor_always_rounds_down(): void
    {
        $engine = new \Price_Calculation_Engine([
            new \Ratio_Rule(),
            new \Rounding_Rule()
        ]);
        $rate = new \Exchange_Rate(1250.0, 1000.0);
        $context = $this->createContext(101.9, 0.0, $rate, ['rounding_type' => 'floor']);

        $result = $engine->calculate($context);

        $this->assertEquals(127.0, $result->new_regular);
    }

    public function test_sale_price_is_cleared_when_greater_than_regular(): void
    {
        $engine = new \Price_Calculation_Engine([new \Ratio_Rule()]);
        $rate = new \Exchange_Rate(2000.0, 1000.0);
        $context = $this->createContext(100.0, 150.0, $rate);

        $result = $engine->calculate($context);

        $this->assertEquals(200.0, $result->new_regular);
        $this->assertEquals(0.0, $result->new_sale);
        $this->assertContains('sale_price_cleared', $result->applied_rules);
    }

    public function test_full_rule_chain(): void
    {
        $engine = new \Price_Calculation_Engine([
            new \Ratio_Rule(),
            new \Margin_Rule(),
            new \Direction_Rule(),
            new \Rounding_Rule()
        ]);
        $rate = new \Exchange_Rate(1250.0, 1000.0);
        $context = $this->createContext(100.0, 0.0, $rate, [
            'margin' => 5,
            'update_direction' => 'bidirectional',
            'rounding_type' => 'integer'
        ]);

        $result = $engine->calculate($context);

        $this->assertGreaterThan(0, $result->new_regular);
        $this->assertTrue($result->has_regular_change());
    }

    public function test_calculation_result_has_change_methods(): void
    {
        $engine = new \Price_Calculation_Engine([new \Ratio_Rule()]);
        $rate = new \Exchange_Rate(1250.0, 1000.0);
        $context = $this->createContext(100.0, 0.0, $rate);

        $result = $engine->calculate($context);

        $this->assertTrue($result->has_regular_change());
        $this->assertFalse($result->has_sale_change());
        $this->assertTrue($result->has_any_change());
    }

    public function test_first_run_ratio_is_one(): void
    {
        $engine = new \Price_Calculation_Engine([new \Ratio_Rule()]);
        $rate = \Exchange_Rate::first_run(1250.0);
        $context = $this->createContext(100.0, 0.0, $rate);

        $result = $engine->calculate($context);

        $this->assertEquals(100.0, $result->new_regular);
        $this->assertEquals(0.0, $result->percentage_change);
    }

    public function test_calculation_result_no_changes(): void
    {
        $engine = new \Price_Calculation_Engine([new \Ratio_Rule()]);
        $rate = \Exchange_Rate::first_run(1250.0);
        $context = $this->createContext(100.0, 0.0, $rate);

        $result = $engine->calculate($context);

        $this->assertFalse($result->has_any_change());
    }

    public function test_ratio_rule_with_zero_price(): void
    {
        $engine = new \Price_Calculation_Engine([new \Ratio_Rule()]);
        $rate = new \Exchange_Rate(1250.0, 1000.0);
        $context = $this->createContext(0.0, 0.0, $rate);

        $result = $engine->calculate($context);

        $this->assertEquals(0.0, $result->new_regular);
    }
}
