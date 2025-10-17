<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Yiisoft\ActiveRecord\AbstractActiveRecord;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Connection\ConnectionProvider;

/**
 * Trait to implement a custom connection name for ActiveRecord.
 *
 * @see AbstractActiveRecord::db()
 */
trait CustomConnectionTrait
{
    private string $connectionName;

    /**
     * Sets the connection name for the ActiveRecord.
     */
    public function withConnectionName(string $connectionName): static
    {
        $new = clone $this;
        $new->connectionName = $connectionName;
        return $new;
    }

    public function db(): ConnectionInterface
    {
        if (!empty($this->connectionName)) {
            return ConnectionProvider::get($this->connectionName);
        }

        return parent::db();
    }
}
