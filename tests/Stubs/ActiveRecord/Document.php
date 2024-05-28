<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

final class Document extends ActiveRecord
{
    public int $id;
    public string $title;
    public string $content;
    public int $version;
    public array $properties;

    public function optimisticLock(): ?string
    {
        return 'version';
    }
}
