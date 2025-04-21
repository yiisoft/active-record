<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordModel;

final class Beta extends ActiveRecordModel
{
    protected int $id;
    protected string $alpha_string_identifier;
    protected Alpha $alpha;

    public function tableName(): string
    {
        return 'beta';
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAlphaStringIdentifier(): string
    {
        return $this->alpha_string_identifier;
    }

    public function relationQuery(string $name): ActiveQueryInterface
    {
        return match ($name) {
            'alpha' => $this->getAlphaQuery(),
            default => parent::relationQuery($name),
        };
    }

    public function getAlpha(): Alpha|null
    {
        return $this->activeRecord()->relation('alpha');
    }

    public function getAlphaQuery(): ActiveQuery
    {
        return $this->activeRecord()->hasOne(Alpha::class, ['string_identifier' => 'alpha_string_identifier']);
    }
}
