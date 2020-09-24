<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\Tests\ActiveQueryFactoryTest as AbstractActiveQueryFactoryTest;

/**
 * @group sqlite
 */
final class ActiveQueryFactoryTest extends AbstractActiveQueryFactoryTest
{
    protected ?string $driverName = 'sqlite';

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
