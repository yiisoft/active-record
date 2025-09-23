<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

/**
 * Trait to implement a custom table name for ActiveRecord.
 *
 * @see ActiveRecordInterface::tableName()
 */
trait CustomTableNameTrait
{
    private string $tableName;

    /**
     * Sets the table name for the ActiveRecord.
     */
    public function withTableName(string $tableName): static
    {
        $new = clone $this;
        $new->tableName = $tableName;
        return $new;
    }

    public function tableName(): string
    {
        return $this->tableName ??= parent::tableName();
    }
}
