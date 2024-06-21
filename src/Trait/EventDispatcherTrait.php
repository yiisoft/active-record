<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\Event\AfterDelete;
use Yiisoft\ActiveRecord\Event\AfterInsert;
use Yiisoft\ActiveRecord\Event\AfterPopulate;
use Yiisoft\ActiveRecord\Event\AfterSave;
use Yiisoft\ActiveRecord\Event\AfterUpdate;
use Yiisoft\ActiveRecord\Event\BeforeDelete;
use Yiisoft\ActiveRecord\Event\BeforeInsert;
use Yiisoft\ActiveRecord\Event\BeforePopulate;
use Yiisoft\ActiveRecord\Event\BeforeSave;
use Yiisoft\ActiveRecord\Event\BeforeUpdate;
use Yiisoft\ActiveRecord\Event\EventDispatcher;

/**
 * Trait to implement event dispatching for ActiveRecord.
 *
 * @see ActiveRecordInterface::delete()
 * @see ActiveRecordInterface::insert()
 * @see ActiveRecordInterface::populateRecord()
 * @see ActiveRecordInterface::update()
 */
trait EventDispatcherTrait
{
    private EventDispatcher $eventDispatcher;

    public function eventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher ??= new EventDispatcher();
    }

    public function delete(): int
    {
        $eventDispatcher = $this->eventDispatcher();
        $eventDispatcher->addListenersFromAttributes($this);
        $eventDispatcher->dispatch(new BeforeDelete($this));

        $result = parent::delete();

        $eventDispatcher->dispatch(new AfterDelete($this, $result));

        return $result;
    }

    public function insert(array|null $properties = null): bool
    {
        $eventDispatcher = $this->eventDispatcher();
        $eventDispatcher->addListenersFromAttributes($this);
        $eventDispatcher->dispatch(new BeforeInsert($this, $properties));

        $result = parent::insert($properties);

        $eventDispatcher->dispatch(new AfterInsert($this, $result));

        return $result;
    }

    public function populateRecord(array|object $data): void
    {
        $eventDispatcher = $this->eventDispatcher();
        $eventDispatcher->addListenersFromAttributes($this);
        $eventDispatcher->dispatch(new BeforePopulate($this, $data));

        parent::populateRecord($data);

        $eventDispatcher->dispatch(new AfterPopulate($this, $data));
    }

    public function save(array|null $properties = null): bool
    {
        $eventDispatcher = $this->eventDispatcher();
        $eventDispatcher->addListenersFromAttributes($this);
        $eventDispatcher->dispatch(new BeforeSave($this, $properties));

        $result = parent::save($properties);

        $eventDispatcher->dispatch(new AfterSave($this, $result));

        return $result;
    }

    public function update(array|null $properties = null): int
    {
        $eventDispatcher = $this->eventDispatcher();
        $eventDispatcher->addListenersFromAttributes($this);
        $eventDispatcher->dispatch(new BeforeUpdate($this, $properties));

        $result = parent::update($properties);

        $eventDispatcher->dispatch(new AfterUpdate($this, $result));

        return $result;
    }
}
