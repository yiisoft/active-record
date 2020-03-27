<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\Connection;

use Yiisoft\Db\Connection\Connection;

final class SqliteConnection extends Connection
{
    private $fixture;

    public function getFixture(): string
    {
        return $this->fixture;
    }

    public function fixture(string $value): void
    {
        $this->fixture = $value;
    }
}
