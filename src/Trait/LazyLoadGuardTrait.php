<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Yiisoft\ActiveRecord\AbstractActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\LazyLoadGuard;

/**
 * Trait to detect N+1 queries caused by lazy loading of relations.
 *
 * @see LazyLoadGuard
 * @see AbstractActiveRecord::retrieveRelation()
 */
trait LazyLoadGuardTrait
{
    protected function retrieveRelation(string $name): ActiveRecordInterface|array|null
    {
        LazyLoadGuard::check($this, $name);

        return parent::retrieveRelation($name);
    }
}
