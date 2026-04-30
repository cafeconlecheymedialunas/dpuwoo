<?php
namespace DPUWoo\Tests\Handlers;

use DPUWoo\Tests\TestCase;
use Brain\Monkey\Functions;

class Update_Prices_Handler_Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('get_current_user_id')->justReturn(1);
    }

    public function test_handle_returns_error_when_api_fails(): void
    {
        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(false);

        $handler = new \Update_Prices_Handler(
            $this->createMock(\Settings_Repository::class),
            $api,
            $this->createMock(\Batch_Processor::class),
            $this->createMock(\Product_Repository_Interface::class),
            $this->createMock(\Logger::class),
            new \Threshold_Policy(),
            $this->createMock(\Log_Repository_Interface::class)
        );

        $cmd = new \Update_Prices_Command(0, false, 'manual');
        $result = $handler->handle($cmd);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('no_rate_available', $result['error']);
    }

    public function test_handle_returns_threshold_not_met(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'dollar_type' => 'oficial',
            'reference_currency' => 'USD',
            'threshold' => 50.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1010.0]);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(fn($type, $ref) => 1000.0);

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $this->createMock(\Batch_Processor::class),
            $this->createMock(\Product_Repository_Interface::class),
            $this->createMock(\Logger::class),
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(0, false, 'manual');
        $result = $handler->handle($cmd);

        $this->assertFalse($result['threshold_met']);
        $this->assertEquals(0, $result['summary']['updated']);
    }

    public function test_handle_threshold_met_with_products(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'dollar_type' => 'oficial',
            'reference_currency' => 'USD',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1050.0]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(1);
        $productRepo->method('get_product_ids_batch')->willReturn([1]);

        $batchResult = new \Batch_Result();
        $batchResult->add_change([
            'product_id' => 1,
            'status' => 'updated',
            'old_regular_price' => 100.0,
            'new_regular_price' => 105.0,
        ]);

        $batchProcessor = $this->createMock(\Batch_Processor::class);
        $batchProcessor->method('process')->willReturn($batchResult);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(fn($type, $ref) => 1000.0);

        $logger = $this->createMock(\Logger::class);
        $logger->method('begin_run_transaction')->willReturn(1);
        $logger->method('add_items_to_transaction')->willReturn(true);
        $logger->method('commit_run_transaction')->willReturn(true);

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $batchProcessor,
            $productRepo,
            $logger,
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(0, false, 'manual');
        $result = $handler->handle($cmd);

        $this->assertTrue($result['threshold_met']);
        $this->assertEquals(1, $result['summary']['updated']);
    }

    public function test_handle_returns_error_when_subsequent_batch_without_run_id(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'dollar_type' => 'oficial',
            'reference_currency' => 'USD',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1050.0]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(51);

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $this->createMock(\Batch_Processor::class),
            $productRepo,
            $this->createMock(\Logger::class),
            new \Threshold_Policy(),
            $this->createMock(\Log_Repository_Interface::class)
        );

        $cmd = new \Update_Prices_Command(1, false, 'manual');
        $result = $handler->handle($cmd);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('missing_run_id', $result['error']);
        $this->assertEquals(0, $result['summary']['updated']);
    }

    public function test_handle_subsequent_batch_transaction_start_failure_returns_error(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'dollar_type' => 'oficial',
            'reference_currency' => 'USD',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1050.0]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(51);
        $productRepo->method('get_product_ids_batch')->willReturn([1]);

        $batchResult = new \Batch_Result();
        $batchResult->add_change([
            'product_id' => 1,
            'status' => 'updated',
            'old_regular_price' => 100.0,
            'new_regular_price' => 105.0,
        ]);

        $batchProcessor = $this->createMock(\Batch_Processor::class);
        $batchProcessor->method('process')->willReturn($batchResult);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(fn($type, $ref) => 1000.0);
        $logRepo->method('begin_transaction')->willReturn(false);

        $logger = $this->createMock(\Logger::class);
        $logger->expects($this->never())->method('add_items_to_transaction');

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $batchProcessor,
            $productRepo,
            $logger,
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(1, false, 'manual', 42);
        $result = $handler->handle($cmd);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('log_persistence_failed', $result['error']);
        $this->assertFalse($result['run_id']);
    }

    public function test_handle_persist_run_failure_returns_error(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'dollar_type' => 'oficial',
            'reference_currency' => 'USD',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1050.0]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(1);
        $productRepo->method('get_product_ids_batch')->willReturn([1]);

        $batchResult = new \Batch_Result();
        $batchResult->add_change([
            'product_id' => 1,
            'status' => 'updated',
            'old_regular_price' => 100.0,
            'new_regular_price' => 105.0,
        ]);

        $batchProcessor = $this->createMock(\Batch_Processor::class);
        $batchProcessor->method('process')->willReturn($batchResult);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(fn($type, $ref) => 1000.0);

        $logger = $this->createMock(\Logger::class);
        $logger->method('begin_run_transaction')->willReturn(false);
        $logger->expects($this->never())->method('add_items_to_transaction');

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $batchProcessor,
            $productRepo,
            $logger,
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(0, false, 'manual');
        $result = $handler->handle($cmd);

        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('log_persistence_failed', $result['error']);
        $this->assertFalse($result['run_id']);
    }

    public function test_handle_subsequent_batch_uses_log_transaction(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'dollar_type' => 'oficial',
            'reference_currency' => 'USD',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1050.0]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(51);
        $productRepo->method('get_product_ids_batch')->willReturn([51]);

        $batchResult = new \Batch_Result();
        $batchResult->add_change([
            'product_id' => 1,
            'status' => 'updated',
            'old_regular_price' => 100.0,
            'new_regular_price' => 105.0,
        ]);

        $batchProcessor = $this->createMock(\Batch_Processor::class);
        $batchProcessor->method('process')->willReturn($batchResult);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(fn($type, $ref) => 1000.0);
        $logRepo->expects($this->once())->method('begin_transaction')->willReturn(true);
        $logRepo->expects($this->once())->method('commit_transaction')->willReturn(true);

        $logger = $this->createMock(\Logger::class);
        $logger->method('add_items_to_transaction')->willReturn(true);
        $logger->expects($this->never())->method('begin_run_transaction');

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $batchProcessor,
            $productRepo,
            $logger,
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(1, false, 'manual', 42);
        $result = $handler->handle($cmd);

        $this->assertTrue($result['threshold_met']);
        $this->assertEquals(1, $result['summary']['updated']);
        $this->assertEquals(42, $result['run_id']);
    }

    public function test_handle_batch_beyond_total_batches_returns_empty(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'dollar_type' => 'oficial',
            'reference_currency' => 'USD',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1050.0]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(50);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(fn($type, $ref) => 1000.0);

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $this->createMock(\Batch_Processor::class),
            $productRepo,
            $this->createMock(\Logger::class),
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(1, false, 'manual', 42);
        $result = $handler->handle($cmd);

        $this->assertTrue($result['threshold_met']);
        $this->assertEmpty($result['changes']);
        $this->assertEquals(0, $result['summary']['updated']);
    }

    public function test_handle_first_run_with_origin_rate(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'dollar_type' => 'oficial',
            'reference_currency' => 'USD',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
            'origin_exchange_rate' => 1000.0,
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1100.0]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(1);
        $productRepo->method('get_product_ids_batch')->willReturn([1]);

        $batchResult = new \Batch_Result();
        $batchResult->add_change([
            'product_id' => 1,
            'status' => 'updated',
            'old_regular_price' => 100.0,
            'new_regular_price' => 110.0,
        ]);

        $batchProcessor = $this->createMock(\Batch_Processor::class);
        $batchProcessor->method('process')->willReturn($batchResult);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(fn($type, $ref) => 0.0);

        $logger = $this->createMock(\Logger::class);
        $logger->method('begin_run_transaction')->willReturn(1);
        $logger->method('add_items_to_transaction')->willReturn(true);
        $logger->method('commit_run_transaction')->willReturn(true);

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $batchProcessor,
            $productRepo,
            $logger,
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(0, false, 'manual');
        $result = $handler->handle($cmd);

        $this->assertTrue($result['threshold_met']);
        $this->assertTrue($result['is_first_run']);
        $this->assertEquals(1.1, $result['ratio']);
    }

    public function test_handle_simulation_does_not_persist(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'dollar_type' => 'oficial',
            'reference_currency' => 'USD',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1050.0]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(1);
        $productRepo->method('get_product_ids_batch')->willReturn([1]);

        $batchResult = new \Batch_Result();
        $batchResult->add_change([
            'product_id' => 1,
            'status' => 'simulated',
            'old_regular_price' => 100.0,
            'new_regular_price' => 105.0,
        ]);

        $batchProcessor = $this->createMock(\Batch_Processor::class);
        $batchProcessor->method('process')->willReturn($batchResult);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(fn($type, $ref) => 1000.0);

        $logger = $this->createMock(\Logger::class);
        $logger->expects($this->never())->method('begin_run_transaction');

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $batchProcessor,
            $productRepo,
            $logger,
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(0, true, 'manual');
        $result = $handler->handle($cmd);

        $this->assertTrue($result['threshold_met']);
        $this->assertTrue($result['summary']['simulated']);
    }

    public function test_handle_no_products_returns_empty(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'dollar_type' => 'oficial',
            'reference_currency' => 'USD',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1050.0]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(0);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(fn($type, $ref) => 1000.0);

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $this->createMock(\Batch_Processor::class),
            $productRepo,
            $this->createMock(\Logger::class),
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(0, false, 'manual');
        $result = $handler->handle($cmd);

        $this->assertTrue($result['threshold_met']);
        $this->assertEquals(0, $result['summary']['updated']);
        $this->assertEmpty($result['changes']);
        $this->assertFalse($result['is_first_run']);
    }

    public function test_handle_compares_same_dollar_type(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'currency' => 'blue',
            'reference_currency' => 'USD',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1300.0]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(0);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(function($type, $ref) {
            $this->assertEquals('blue', $type);
            $this->assertEquals('USD', $ref);
            return 1250.0;
        });

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $this->createMock(\Batch_Processor::class),
            $productRepo,
            $this->createMock(\Logger::class),
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(0, false, 'manual');
        $result = $handler->handle($cmd);

        $this->assertTrue($result['threshold_met']);
        $this->assertEquals(1300.0, $result['current']);
        $this->assertEquals(1250.0, $result['previous']);
        $this->assertEqualsWithDelta(4.0, $result['percentage_change'], 0.1);
    }

    public function test_handle_first_run_when_no_log_for_type(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'dollar_type' => 'oficial',
            'reference_currency' => 'USD',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1405.0]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(0);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(function($type, $ref) {
            $this->assertEquals('oficial', $type);
            $this->assertEquals('USD', $ref);
            return 0.0;
        });

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $this->createMock(\Batch_Processor::class),
            $productRepo,
            $this->createMock(\Logger::class),
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(0, false, 'manual');
        $result = $handler->handle($cmd);

        $this->assertTrue($result['threshold_met']);
        $this->assertTrue($result['is_first_run']);
        $this->assertEquals(1.0, $result['ratio']);
    }

    public function test_handle_compares_same_dollar_type_and_reference(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'currency' => 'bolsa',
            'reference_currency' => 'USD',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1420.0]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(0);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(function($type, $ref) {
            $this->assertEquals('bolsa', $type);
            $this->assertEquals('USD', $ref);
            return 1400.0;
        });

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $this->createMock(\Batch_Processor::class),
            $productRepo,
            $this->createMock(\Logger::class),
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(0, false, 'manual');
        $result = $handler->handle($cmd);

        $this->assertTrue($result['threshold_met']);
        $this->assertEquals(1420.0, $result['current']);
        $this->assertEquals(1400.0, $result['previous']);
        $this->assertEqualsWithDelta(1.43, $result['percentage_change'], 0.1);
    }

    public function test_handle_first_run_for_new_currency(): void
    {
        $settings = $this->createMock(\Settings_Repository::class);
        $settings->method('get_for_context')->willReturn([
            'currency' => 'euro',
            'reference_currency' => 'EUR',
            'threshold' => 1.0,
            'threshold_max' => 0,
            'update_direction' => 'bidirectional',
        ]);

        $api = $this->createMock(\API_Client::class);
        $api->method('get_rate')->willReturn(['value' => 1.08]);

        $productRepo = $this->createMock(\Product_Repository_Interface::class);
        $productRepo->method('count_all_products')->willReturn(0);

        $logRepo = $this->createMock(\Log_Repository_Interface::class);
        $logRepo->method('get_last_applied_rate')->willReturnCallback(function($type, $ref) {
            $this->assertEquals('euro', $type);
            $this->assertEquals('EUR', $ref);
            return 0.0;
        });

        $handler = new \Update_Prices_Handler(
            $settings,
            $api,
            $this->createMock(\Batch_Processor::class),
            $productRepo,
            $this->createMock(\Logger::class),
            new \Threshold_Policy(),
            $logRepo
        );

        $cmd = new \Update_Prices_Command(0, false, 'manual');
        $result = $handler->handle($cmd);

        $this->assertTrue($result['threshold_met']);
        $this->assertTrue($result['is_first_run']);
        $this->assertEquals(1.0, $result['ratio']);
    }
}
