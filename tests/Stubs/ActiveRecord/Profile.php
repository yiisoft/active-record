<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class Profile.
 */
final class Profile extends ActiveRecord
{
    public const TABLE_NAME = 'profile';

    protected int $id;
    protected string $description;

    public function tableName(): string
    {
        return self::TABLE_NAME;
    }

    public function getId(): int
    {
        return $this->id;
    }
}
