<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\OptimisticLockInterface;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

/**
 * @property int $id
 * @property string $title
 * @property string $content
 * @property int $version
 * @property array $properties
 */
final class Document extends MagicActiveRecord implements OptimisticLockInterface
{
    public function optimisticLockPropertyName(): string
    {
        return 'version';
    }
}
