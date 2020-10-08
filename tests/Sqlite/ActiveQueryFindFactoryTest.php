<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\Tests\ActiveQueryFindFactoryTest as AbstractActiveQueryFindFactoryTest;

/**
 * @group sqlite
 */
final class ActiveQueryFindFactoryTest extends AbstractActiveQueryFindFactoryTest
{
    protected string $driverName = 'sqlite';

    public function setUp(): void
    {
        parent::setUp();

        $this->arFactory->withConnection($this->sqliteConnection);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->sqliteConnection->close();

        unset($this->arFactory, $this->sqliteConnection);
    }
}
