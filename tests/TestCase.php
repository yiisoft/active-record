<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * setUp
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * tearDown
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
