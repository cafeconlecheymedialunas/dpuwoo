<?php
namespace DPUWoo\Tests\Engine;

use DPUWoo\Tests\TestCase;

class Batch_Processor_Test extends TestCase
{
    public function test_process_empty_array_returns_empty_result(): void
    {
        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $engine = $this->createMock(\Price_Calculation_Engine::class);
        
        $processor = new \Batch_Processor($productRepo, $engine);
        $rate = \Exchange_Rate::first_run(1250.0);

        $result = $processor->process([], $rate, []);

        $this->assertEmpty($result->get_changes());
        $this->assertEquals(0, $result->get_updated());
    }

    public function test_batch_result_merge_combines_results(): void
    {
        $result1 = new \Batch_Result();
        $result1->add_change(['status' => 'updated']);
        
        $result2 = new \Batch_Result();
        $result2->add_change(['status' => 'updated']);
        $result2->add_change(['status' => 'error', 'reason' => 'Test error']);
        
        $result1->merge($result2);
        
        $this->assertEquals(2, $result1->get_updated());
        $this->assertEquals(1, $result1->get_errors());
    }

    public function test_batch_result_to_summary(): void
    {
        $result = new \Batch_Result();
        $result->add_change(['status' => 'updated']);
        $result->add_change(['status' => 'updated']);
        $result->add_change(['status' => 'skipped']);
        $result->add_change(['status' => 'error', 'reason' => 'Test']);
        
        $summary = $result->to_summary(false);
        
        $this->assertEquals(2, $summary['updated']);
        $this->assertEquals(1, $summary['errors']);
        $this->assertEquals(1, $summary['skipped']);
        $this->assertFalse($summary['simulated']);
    }

    public function test_batch_result_errors_map(): void
    {
        $result = new \Batch_Result();
        $result->add_change(['status' => 'error', 'reason' => 'Test error']);
        $result->add_change(['status' => 'error', 'reason' => 'Test error']);
        $result->add_change(['status' => 'error', 'reason' => 'Another error']);
        
        $errorsMap = $result->get_errors_map();
        
        $this->assertEquals(2, $errorsMap['Test error']);
        $this->assertEquals(1, $errorsMap['Another error']);
    }

    public function test_batch_result_handles_skipped(): void
    {
        $result = new \Batch_Result();
        $result->add_change(['status' => 'updated']);
        $result->add_change(['status' => 'skipped']);
        
        $this->assertEquals(1, $result->get_updated());
        $this->assertEquals(1, $result->get_skipped());
    }

    public function test_batch_result_to_summary_simulation(): void
    {
        $result = new \Batch_Result();
        $result->add_change(['status' => 'simulated']);
        
        $summary = $result->to_summary(true);
        
        $this->assertEquals(1, $summary['updated']);
        $this->assertTrue($summary['simulated']);
    }
}
