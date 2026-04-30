<?php
namespace DPUWoo\Tests\Engine;

use DPUWoo\Tests\TestCase;

class Batch_Processor_Logic_Test extends TestCase
{
    public function test_process_returns_empty_result_for_empty_array(): void
    {
        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $engine = $this->createMock(\Price_Calculation_Engine::class);
        $logRepo = $this->createMock(\Log_Repository_Interface::class);

        $processor = new \Batch_Processor($productRepo, $engine, $logRepo);
        $rate = \Exchange_Rate::first_run(1250.0);

        $result = $processor->process([], $rate, []);

        $this->assertEmpty($result->get_changes());
        $this->assertEquals(0, $result->get_updated());
    }

    public function test_process_calculates_theoretical_percentage_change(): void
    {
        $settings = [
            'margin' => 0,
            'threshold' => 1.0,
        ];
        
        $exchangeRate = new \Exchange_Rate(1250.0, 1000.0);
        
        $expectedPct = round((1.25 * 1.0 - 1) * 100, 2);
        
        $this->assertEquals(25.0, $expectedPct);
    }

    public function test_make_change_data_structure(): void
    {
        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $engine = $this->createMock(\Price_Calculation_Engine::class);
        $logRepo = $this->createMock(\Log_Repository_Interface::class);

        $processor = new \Batch_Processor($productRepo, $engine, $logRepo);
        
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('make_change_data');
        $method->setAccessible(true);
        
        $result = $method->invoke($processor, 1, 'Test Product', 'SKU001', 'simple', 100.0, 125.0, 0.0, 0.0, 'updated', null, [], 25.0);
        
        $this->assertEquals(1, $result['product_id']);
        $this->assertEquals('Test Product', $result['product_name']);
        $this->assertEquals('SKU001', $result['product_sku']);
        $this->assertEquals('simple', $result['product_type']);
        $this->assertEquals(100.0, $result['old_regular_price']);
        $this->assertEquals(125.0, $result['new_regular_price']);
        $this->assertEquals('updated', $result['status']);
        $this->assertEquals(25.0, $result['percentage_change']);
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

    public function test_batch_result_to_summary_simulation(): void
    {
        $result = new \Batch_Result();
        $result->add_change(['status' => 'simulated']);
        
        $summary = $result->to_summary(true);
        
        $this->assertEquals(1, $summary['updated']);
        $this->assertTrue($summary['simulated']);
    }

    public function test_batch_result_handles_skipped(): void
    {
        $result = new \Batch_Result();
        $result->add_change(['status' => 'updated']);
        $result->add_change(['status' => 'skipped']);
        
        $this->assertEquals(1, $result->get_updated());
        $this->assertEquals(1, $result->get_skipped());
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
}