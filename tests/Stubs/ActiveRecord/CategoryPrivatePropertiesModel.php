<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;
use Yiisoft\ActiveRecord\Trait\PrivatePropertiesTrait;

final class CategoryPrivatePropertiesModel extends ActiveRecord
{
    use PrivatePropertiesTrait;

    private ?int $id = null;
    private ?string $name = null;

    public function tableName(): string
    {
        return 'category';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}
