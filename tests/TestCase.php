<?php
namespace DPUWoo\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Brain\Monkey\Functions;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    protected function mockWpFunction(string $function, mixed $return, ...$args): void
    {
        if (empty($args)) {
            Functions\stubFunction($function)->andReturn($return);
        } else {
            Functions\when($function)->justReturn($return);
        }
    }

    protected function mockWpFunctionWithArgs(string $function, mixed $return, array $args): void
    {
        Functions\when($function)->alias(function(...$a) use ($return, $args) {
            return $return;
        });
    }
}
