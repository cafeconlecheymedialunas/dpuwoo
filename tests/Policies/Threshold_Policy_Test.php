<?php
namespace DPUWoo\Tests\Policies;

use DPUWoo\Tests\TestCase;

class Threshold_Policy_Test extends TestCase
{
    private \Threshold_Policy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new \Threshold_Policy();
    }

    public function test_first_run_always_returns_true(): void
    {
        $rate = new \Exchange_Rate(1000.0, 1000.0);

        $this->assertTrue($this->policy->should_update($rate, 50.0, 0, 'bidirectional', true));
    }

    public function test_up_only_allows_positive_change(): void
    {
        $rate_up = new \Exchange_Rate(1100.0, 1000.0);
        $rate_down = new \Exchange_Rate(900.0, 1000.0);
        $rate_same = new \Exchange_Rate(1000.0, 1000.0);

        $this->assertTrue($this->policy->should_update($rate_up, 0, 0, 'up_only', false));
        $this->assertFalse($this->policy->should_update($rate_down, 0, 0, 'up_only', false));
        $this->assertFalse($this->policy->should_update($rate_same, 0, 0, 'up_only', false));
    }

    public function test_down_only_allows_negative_change(): void
    {
        $rate_up = new \Exchange_Rate(1100.0, 1000.0);
        $rate_down = new \Exchange_Rate(900.0, 1000.0);
        $rate_same = new \Exchange_Rate(1000.0, 1000.0);

        $this->assertFalse($this->policy->should_update($rate_up, 0, 0, 'down_only', false));
        $this->assertTrue($this->policy->should_update($rate_down, 0, 0, 'down_only', false));
        $this->assertFalse($this->policy->should_update($rate_same, 0, 0, 'down_only', false));
    }

    public function test_bidirectional_allows_any_direction(): void
    {
        $rate_up = new \Exchange_Rate(1100.0, 1000.0);
        $rate_down = new \Exchange_Rate(900.0, 1000.0);

        $this->assertTrue($this->policy->should_update($rate_up, 0, 0, 'bidirectional', false));
        $this->assertTrue($this->policy->should_update($rate_down, 0, 0, 'bidirectional', false));
    }

    public function test_threshold_min_must_be_met(): void
    {
        $rate_small = new \Exchange_Rate(1010.0, 1000.0);
        $rate_large = new \Exchange_Rate(1050.0, 1000.0);

        $this->assertFalse($this->policy->should_update($rate_small, 2.0, 0, 'bidirectional', false));
        $this->assertTrue($this->policy->should_update($rate_large, 2.0, 0, 'bidirectional', false));
    }

    public function test_threshold_max_blocks_excessive_changes(): void
    {
        $rate_normal = new \Exchange_Rate(1020.0, 1000.0);
        $rate_excessive = new \Exchange_Rate(1500.0, 1000.0);

        $this->assertTrue($this->policy->should_update($rate_normal, 0, 100.0, 'bidirectional', false));
        $this->assertFalse($this->policy->should_update($rate_excessive, 0, 30.0, 'bidirectional', false));
    }

    public function test_zero_threshold_max_means_no_limit(): void
    {
        $rate = new \Exchange_Rate(2000.0, 1000.0);

        $this->assertTrue($this->policy->should_update($rate, 0, 0, 'bidirectional', false));
    }

    public function test_combined_direction_and_threshold(): void
    {
        $rate_up_small = new \Exchange_Rate(1005.0, 1000.0);
        $rate_up_large = new \Exchange_Rate(1050.0, 1000.0);
        $rate_down = new \Exchange_Rate(900.0, 1000.0);

        $this->assertFalse($this->policy->should_update($rate_up_small, 1.0, 0, 'up_only', false));
        $this->assertTrue($this->policy->should_update($rate_up_large, 1.0, 0, 'up_only', false));
        $this->assertFalse($this->policy->should_update($rate_down, 1.0, 0, 'up_only', false));
    }

    public function test_combined_direction_and_threshold_max(): void
    {
        $rate_up_excessive = new \Exchange_Rate(1100.0, 1000.0);
        $rate_down = new \Exchange_Rate(900.0, 1000.0);

        $this->assertFalse($this->policy->should_update($rate_up_excessive, 0, 5.0, 'up_only', false));
        $this->assertFalse($this->policy->should_update($rate_down, 0, 5.0, 'up_only', false));
    }
}
