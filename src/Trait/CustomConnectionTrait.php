<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Yiisoft\ActiveRecord\AbstractActiveRecord;
use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\Db\Connection\ConnectionInterface;

/**
 * Trait to implement custom connection name for ActiveRecord.
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
