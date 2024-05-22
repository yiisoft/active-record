<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * @property int $id
 * @property string $string_identifier
 */
final class Alpha extends ActiveRecord
{
    public const TABLE_NAME = 'alpha';

    protected int $id;

    protected string $string_identifier;

    public function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getStringIdentifier(): string
    {
        return $this->string_identifier;
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'betas' => $this->getBetasQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getBetas(): array|null
    {
        return $this->relation('betas');
    }

    public function getBetasQuery(): ActiveQuery
    {
        return $this->hasMany(Beta::class, ['alpha_string_identifier' => 'string_identifier']);
    }
}
