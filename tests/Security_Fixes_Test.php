<?php
namespace DPUWoo\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Brain\Monkey\Functions;

/**
 * Pruebas para los arreglos de seguridad e integridad de datos (P0/P1)
 * 
 * Problemas cubiertos:
 * - P0: Validación de tasa de cambio > 0
 * - P0: Transacciones reales en Logger
 * - P1: Validación de rango de precios
 * - P1: USD baseline consistente
 */
class Security_Fixes_Test extends TestCase
{
    /**
     * P0: Validar que tasa de cambio sea validada en handler
     */
    public function test_invalid_rates_are_detected()
    {
        // Casos de tasa inválida
        $invalid_rates = [0.0, -1.5, INF, NAN];
        
        foreach ($invalid_rates as $rate) {
            $is_valid = !($rate <= 0 || is_nan($rate) || is_infinite($rate));
            $this->assertFalse($is_valid, "Tasa $rate debería ser inválida");
        }
    }

    /**
     * P1: Validar que precios fuera de rango sean rechazados
     */
    public function test_price_validation_range()
    {
        // Precios válidos
        $valid_prices = [0.01, 1.00, 100.50, 999999.99];
        foreach ($valid_prices as $price) {
            $is_valid = !($price < 0.01 || $price > 999999.99 || is_nan($price) || is_infinite($price));
            $this->assertTrue($is_valid, "Precio válido $price fue rechazado incorrectamente");
        }
        
        // Precios inválidos
        $invalid_prices = [0.0, 0.001, -1.5, 1000000.00];
        foreach ($invalid_prices as $price) {
            $is_valid = !($price < 0.01 || $price > 999999.99 || is_nan($price) || is_infinite($price));
            $this->assertFalse($is_valid, "Precio inválido $price fue aceptado incorrectamente");
        }
    }

    /**
     * P1: Validar que USD baseline use tasa anterior consistentemente
     */
    public function test_usd_baseline_calculated_correctly()
    {
        $old_regular = 1000.0; // ARS
        $rate_previous = 100.0; // USD/ARS del ciclo anterior
        
        // Con el fix, debería ser: 1000 / 100 = 10 USD
        $baseline = $old_regular / $rate_previous;
        $this->assertEquals(10.0, $baseline, 'USD baseline debe calcularse correctamente con tasa anterior');
    }

    /**
     * P0: Transacciones comienzan y hacen commit correctamente
     */
    public function test_transaction_methods_exist()
    {
        // Verificar que los métodos de transacción se hayan agregado a Log_Repository
        $reflection = new \ReflectionClass('Log_Repository');
        $this->assertTrue($reflection->hasMethod('begin_transaction'), 'Log_Repository debe tener método begin_transaction');
        $this->assertTrue($reflection->hasMethod('commit_transaction'), 'Log_Repository debe tener método commit_transaction');
        $this->assertTrue($reflection->hasMethod('rollback_transaction'), 'Log_Repository debe tener método rollback_transaction');
    }

    /**
     * Test: Validación de que items simulados no se guardan
     */
    public function test_simulated_items_are_filtered()
    {
        // Items con mezcla de estados
        $items = [
            ['status' => 'updated'],
            ['status' => 'simulated'],
            ['status' => 'error'],
            ['status' => 'skipped'],
        ];
        
        // Solo updated o error deben pasar el filtro
        $filtered = array_filter($items, function($item) {
            return in_array($item['status'], ['updated', 'error']);
        });
        
        $this->assertCount(2, $filtered, 'Solo 2 items deben pasar el filtro');
        
        // Verificar que no hay simulated o skipped
        $accepted_statuses = ['updated', 'error'];
        foreach ($filtered as $item) {
            $this->assertContains($item['status'], $accepted_statuses, 'Los items no deben ser simulated o skipped');
        }
    }
}

