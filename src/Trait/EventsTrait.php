<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Closure;
use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Event\AfterCreateQuery;
use Yiisoft\ActiveRecord\Event\AfterDelete;
use Yiisoft\ActiveRecord\Event\AfterInsert;
use Yiisoft\ActiveRecord\Event\AfterPopulate;
use Yiisoft\ActiveRecord\Event\AfterSave;
use Yiisoft\ActiveRecord\Event\AfterUpdate;
use Yiisoft\ActiveRecord\Event\AfterUpsert;
use Yiisoft\ActiveRecord\Event\BeforeCreateQuery;
use Yiisoft\ActiveRecord\Event\BeforeDelete;
use Yiisoft\ActiveRecord\Event\BeforeInsert;
use Yiisoft\ActiveRecord\Event\BeforePopulate;
use Yiisoft\ActiveRecord\Event\BeforeSave;
use Yiisoft\ActiveRecord\Event\BeforeUpdate;
use Yiisoft\ActiveRecord\Event\BeforeUpsert;
use Yiisoft\ActiveRecord\Event\EventDispatcherProvider;

use function is_string;

/**
 * Trait to implement event dispatching for ActiveRecord.
 *
 * @see ActiveRecordInterface::delete()
 * @see ActiveRecordInterface::insert()
 * @see ActiveRecordInterface::populateRecord()
 * @see ActiveRecordInterface::query()
 * @see ActiveRecordInterface::save()
 * @see ActiveRecordInterface::update()
 * @see ActiveRecordInterface::upsert()
 */
trait EventsTrait
{
    public function delete(): int
    {
        $eventDispatcher = EventDispatcherProvider::get(static::class);
        $eventDispatcher->dispatch($event = new BeforeDelete($this));

        if ($event->isDefaultPrevented()) {
            return $event->getReturnValue() ?? 0;
        }

        $result = parent::delete();

        $eventDispatcher->dispatch(new AfterDelete($this, $result));

        return $result;
    }

    public function insert(array|null $properties = null): bool
    {
        $eventDispatcher = EventDispatcherProvider::get(static::class);
        $eventDispatcher->dispatch($event = new BeforeInsert($this, $properties));

        if ($event->isDefaultPrevented()) {
            return $event->getReturnValue() ?? false;
        }

        $result = parent::insert($properties);

        $eventDispatcher->dispatch(new AfterInsert($this, $result));

        return $result;
    }

    public function populateRecord(array|object $data): static
    {
        $eventDispatcher = EventDispatcherProvider::get(static::class);
        $eventDispatcher->dispatch($event = new BeforePopulate($this, $data));

        if ($event->isDefaultPrevented()) {
            return $this;
        }

        parent::populateRecord($data);

        $eventDispatcher->dispatch(new AfterPopulate($this, $data));

        return $this;
    }

    public static function query(ActiveRecordInterface|Closure|null|string $modelClass = null): ActiveQueryInterface
    {
        $model = match (true) {
            $modelClass === null => new static(),
            is_string($modelClass) => new $modelClass(),
            $modelClass instanceof ActiveRecordInterface => $modelClass,
            default => ($modelClass)(),
        };

        $eventDispatcher = EventDispatcherProvider::get($model::class);
        $eventDispatcher->dispatch($event = new BeforeCreateQuery($model));

        if ($event->isDefaultPrevented()) {
            return $event->getReturnValue();
        }

        $query = parent::query($model);

        $eventDispatcher->dispatch(new AfterCreateQuery($model, $query));

        return $query;
    }

    public function save(array|null $properties = null): bool
    {
        $eventDispatcher = EventDispatcherProvider::get(static::class);
        $eventDispatcher->dispatch($event = new BeforeSave($this, $properties));

        if ($event->isDefaultPrevented()) {
            return $event->getReturnValue() ?? false;
        }

        $result = parent::save($properties);

        $eventDispatcher->dispatch(new AfterSave($this, $result));

        return $result;
    }

    public function update(array|null $properties = null): int
    {
        $eventDispatcher = EventDispatcherProvider::get(static::class);
        $eventDispatcher->dispatch($event = new BeforeUpdate($this, $properties));

        if ($event->isDefaultPrevented()) {
            return $event->getReturnValue() ?? 0;
        }

        $result = parent::update($properties);

        $eventDispatcher->dispatch(new AfterUpdate($this, $result));

        return $result;
    }

    public function upsert(array|null $insertProperties = null, array|bool $updateProperties = true): bool
    {
        $eventDispatcher = EventDispatcherProvider::get(static::class);
        $eventDispatcher->dispatch($event = new BeforeUpsert($this, $insertProperties, $updateProperties));

        if ($event->isDefaultPrevented()) {
            return $event->getReturnValue() ?? false;
        }

        $result = parent::upsert($insertProperties, $updateProperties);

        $eventDispatcher->dispatch(new AfterUpsert($this, $result));

        return $result;
    }
}
