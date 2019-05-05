<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\ActiveRecord;

use yii\base\Event;

/**
 * ActiveRecordEvent represents the event parameter used for an active record event.
 *
 * @author Fabrizio Caldarelli <fabrizio.caldarelli@gmail.com>
 * @since 3.0
 */
class ActiveRecordEvent extends Event
{
    /**
     * @event raised after executing init action
     */
    const INIT = 'yii\base\Event\ActiveRecordEvent::INIT';
    /**
     * @event raised after executing find action
     */
    const AFTER_FIND = 'yii\base\Event\ActiveRecordEvent::AFTER_FIND';
    /**
     * @event raised after executing find action
     */
    const AFTER_REFRESH = 'yii\base\Event\ActiveRecordEvent::AFTER_REFRESH';
    /**
     * @event event raised at the beginning of [[delete()]]. You may set
     * [[Event::isValid]] to be false to stop the validation.
     */
    const BEFORE_DELETE = 'yii\base\Event\ActiveRecordEvent::BEFORE_DELETE';
    /**
     * @event raised after executing delete action
     */
    const AFTER_DELETE = 'yii\base\Event\ActiveRecordEvent::AFTER_DELETE';

    /**
     * Creates AFTER FIND event.
     * @return self created event
     */
    public static function init(): self
    {
        return new static(static::INIT);
    }

    /**
     * Creates AFTER FIND event.
     * @return self created event
     */
    public static function afterFind(): self
    {
        return new static(static::AFTER_FIND);
    }

    /**
     * Creates AFTER FIND event.
     * @return self created event
     */
    public static function afterRefresh(): self
    {
        return new static(static::AFTER_REFRESH);
    }

    /**
     * Creates BEFORE DELETE event.
     * @return self created event
     */
    public static function beforeDelete(): self
    {
        return new static(static::BEFORE_DELETE);
    }

    /**
     * Creates AFTER DELETE event.
     * @return self created event
     */
    public static function afterDelete(): self
    {
        return new static(static::AFTER_DELETE);
    }
}
