<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use ReflectionClass;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Exception\InvalidArgumentException;

/**
 * The base class for active record models.
 */
abstract class AbstractActiveRecordModel implements ActiveRecordModelInterface
{
    private ActiveRecord $activeRecord;

    public function activeRecord(): ActiveRecord
    {
        /** @psalm-suppress RedundantPropertyInitializationCheck */
        return $this->activeRecord ??= new ActiveRecord($this);
    }

    public function db(): ConnectionInterface
    {
        return ConnectionProvider::get();
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        throw new InvalidArgumentException(static::class . ' has no relation named "' . $name . '".');
    }

    public function tableName(): string
    {
        $name = (new ReflectionClass($this))->getShortName();
        /** @var string $name */
        $name = preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $name);
        $name = strtolower(ltrim($name, '_'));

        return '{{%' . $name . '}}';
    }
}
