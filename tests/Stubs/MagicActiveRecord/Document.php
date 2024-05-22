<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord;

use Yiisoft\ActiveRecord\MagicActiveRecord;

/**
 * @property int $id
 * @property string $title
 * @property string $content
 * @property int $version
 * @property array $properties
 */
final class Document extends MagicActiveRecord
{
    public function optimisticLock(): ?string
    {
        return 'version';
    }
}
