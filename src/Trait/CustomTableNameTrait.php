<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

trait CustomTableNameTrait
{
    private string $tableName;

    public function withTableName($tableName): static
    {
        $new = clone $this;
        $new->tableName = $tableName;
        return $new;
    }

    public function getTableName(): string
    {
        return $this->tableName ??= parent::getTableName();
    }
}
