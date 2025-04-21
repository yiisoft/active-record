<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordModel;

final class Alpha extends ActiveRecordModel
{
    public const TABLE_NAME = 'alpha';

    protected int $id;

    protected string $string_identifier;

    public function tableName(): string
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
        return $this->activeRecord()->relation('betas');
    }

    public function getBetasQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasMany(Beta::class, ['alpha_string_identifier' => 'string_identifier']);
    }
}
