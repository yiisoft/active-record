<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * @property int $id
 * @property string $title
 * @property string $content
 * @property int $version
 * @property array $properties
 */
class Document extends ActiveRecord
{
    public function optimisticLock(): ?string
    {
        return 'version';
    }
}
