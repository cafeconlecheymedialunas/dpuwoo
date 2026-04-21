<?php
namespace DPUWoo\Tests\ValueObjects;

use DPUWoo\Tests\TestCase;

class Calculation_Result_Test extends TestCase
{
    public function test_constructor_stores_values(): void
    {
        $result = new \Calculation_Result(
            new_regular: 125.0,
            new_sale: 100.0,
            old_regular: 100.0,
            old_sale: 80.0,
            applied_rules: ['ratio_1.25', 'margin_10']
        );

        $this->assertEquals(125.0, $result->new_regular);
        $this->assertEquals(100.0, $result->new_sale);
        $this->assertEquals(100.0, $result->old_regular);
        $this->assertEquals(80.0, $result->old_sale);
        $this->assertEquals(['ratio_1.25', 'margin_10'], $result->applied_rules);
    }

    public function test_has_regular_change_returns_true_when_changed(): void
    {
        $result = new \Calculation_Result(
            new_regular: 125.0,
            new_sale: 0.0,
            old_regular: 100.0,
            old_sale: 0.0,
            applied_rules: []
        );

        $this->assertTrue($result->has_regular_change());
    }

    public function test_has_regular_change_returns_false_when_same(): void
    {
        $result = new \Calculation_Result(
            new_regular: 100.0,
            new_sale: 0.0,
            old_regular: 100.0,
            old_sale: 0.0,
            applied_rules: []
        );

        $this->assertFalse($result->has_regular_change());
    }

    public function test_has_sale_change_returns_true_when_changed(): void
    {
        $result = new \Calculation_Result(
            new_regular: 100.0,
            new_sale: 90.0,
            old_regular: 100.0,
            old_sale: 80.0,
            applied_rules: []
        );

        $this->assertTrue($result->has_sale_change());
    }

    public function test_has_sale_change_returns_false_when_same(): void
    {
        $result = new \Calculation_Result(
            new_regular: 100.0,
            new_sale: 80.0,
            old_regular: 100.0,
            old_sale: 80.0,
            applied_rules: []
        );

        $this->assertFalse($result->has_sale_change());
    }

    public function test_has_any_change_returns_true_when_any_changed(): void
    {
        $result = new \Calculation_Result(
            new_regular: 100.0,
            new_sale: 0.0,
            old_regular: 100.0,
            old_sale: 0.0,
            applied_rules: []
        );

        $this->assertFalse($result->has_any_change());

        $result2 = new \Calculation_Result(
            new_regular: 125.0,
            new_sale: 0.0,
            old_regular: 100.0,
            old_sale: 0.0,
            applied_rules: []
        );

        $this->assertTrue($result2->has_any_change());
    }

    public function test_percentage_change_calculation(): void
    {
        $result = new \Calculation_Result(
            new_regular: 125.0,
            new_sale: 0.0,
            old_regular: 100.0,
            old_sale: 0.0,
            applied_rules: []
        );

        $this->assertEquals(25.0, $result->percentage_change);
    }

    public function test_percentage_change_is_zero_when_no_change(): void
    {
        $result = new \Calculation_Result(
            new_regular: 100.0,
            new_sale: 0.0,
            old_regular: 100.0,
            old_sale: 0.0,
            applied_rules: []
        );

        $this->assertEquals(0.0, $result->percentage_change);
    }

    public function test_new_sale_zero_clears_sale(): void
    {
        $result = new \Calculation_Result(
            new_regular: 100.0,
            new_sale: 0.0,
            old_regular: 100.0,
            old_sale: 80.0,
            applied_rules: ['sale_price_cleared']
        );

        $this->assertEquals(0.0, $result->new_sale);
        $this->assertContains('sale_price_cleared', $result->applied_rules);
    }
}