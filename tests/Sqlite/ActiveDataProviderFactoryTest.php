<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Sqlite;

use Yiisoft\ActiveRecord\Tests\ActiveDataProviderFactoryTest as AbstractActiveDataProviderFactoryTest;

/**
 * @group sqlite
 */
final class ActiveDataProviderFactoryTest extends AbstractActiveDataProviderFactoryTest
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
