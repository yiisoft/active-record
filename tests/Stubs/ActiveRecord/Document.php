<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecordModel;
use Yiisoft\ActiveRecord\OptimisticLockInterface;

final class Document extends ActiveRecordModel implements OptimisticLockInterface
{
    public int $id;
    public string $title;
    public string $content;
    public int $version;
    public array $properties;

    public function optimisticLockPropertyName(): string
    {
        return 'version';
    }
}
