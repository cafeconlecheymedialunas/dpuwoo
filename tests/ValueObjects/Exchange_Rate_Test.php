<?php
namespace DPUWoo\Tests\ValueObjects;

use DPUWoo\Tests\TestCase;

class Exchange_Rate_Test extends TestCase
{
    public function test_constructor_calculates_ratio_and_percentage_change(): void
    {
        $rate = new \Exchange_Rate(1250.0, 1000.0);

        $this->assertEquals(1.25, $rate->ratio);
        $this->assertEquals(25.0, $rate->percentage_change);
        $this->assertEquals(1250.0, $rate->current);
        $this->assertEquals(1000.0, $rate->previous);
    }

    public function test_constructor_handles_decrease(): void
    {
        $rate = new \Exchange_Rate(900.0, 1000.0);

        $this->assertEquals(0.9, $rate->ratio);
        $this->assertEquals(-10.0, $rate->percentage_change);
    }

    public function test_constructor_handles_no_change(): void
    {
        $rate = new \Exchange_Rate(1000.0, 1000.0);

        $this->assertEquals(1.0, $rate->ratio);
        $this->assertEquals(0.0, $rate->percentage_change);
    }

    public function test_constructor_handles_zero_previous_rate(): void
    {
        $rate = new \Exchange_Rate(1250.0, 0.0);

        $this->assertEquals(1.0, $rate->ratio);
        $this->assertEquals(0.0, $rate->percentage_change);
    }

    public function test_first_run_creates_rate_with_same_current_and_previous(): void
    {
        $rate = \Exchange_Rate::first_run(1250.0);

        $this->assertEquals(1.0, $rate->ratio);
        $this->assertEquals(0.0, $rate->percentage_change);
        $this->assertEquals(1250.0, $rate->current);
        $this->assertEquals(1250.0, $rate->previous);
    }

    public function test_get_abs_percentage_change(): void
    {
        $rate_up = new \Exchange_Rate(1100.0, 1000.0);
        $rate_down = new \Exchange_Rate(900.0, 1000.0);

        $this->assertEquals(10.0, $rate_up->get_abs_percentage_change());
        $this->assertEquals(10.0, $rate_down->get_abs_percentage_change());
    }

    public function test_meets_threshold_returns_true_when_change_exceeds(): void
    {
        $rate = new \Exchange_Rate(1050.0, 1000.0);

        $this->assertTrue($rate->meets_threshold(1.0));
        $this->assertFalse($rate->meets_threshold(10.0));
    }

    public function test_meets_threshold_with_zero_threshold(): void
    {
        $rate = new \Exchange_Rate(1001.0, 1000.0);

        $this->assertTrue($rate->meets_threshold(0));
    }

    public function test_to_array_returns_correct_structure(): void
    {
        $rate = new \Exchange_Rate(1250.0, 1000.0);
        $arr = $rate->to_array();

        $this->assertArrayHasKey('current', $arr);
        $this->assertArrayHasKey('previous', $arr);
        $this->assertArrayHasKey('ratio', $arr);
        $this->assertArrayHasKey('percentage_change', $arr);
        $this->assertEquals(1250.0, $arr['current']);
        $this->assertEquals(1000.0, $arr['previous']);
        $this->assertEquals(1.25, $arr['ratio']);
        $this->assertEquals(25.0, $arr['percentage_change']);
    }
}
