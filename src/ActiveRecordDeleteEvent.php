<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\activerecord;

use yii\base\Event;


/**
 * ActiveRecordDeleteEvent represents the event parameter used for an active record delete event.
 *
 * @author Fabrizio Caldarelli <fabrizio.caldarelli@gmail.com>
 * @since 3.0
 */
class ActiveRecordDeleteEvent extends Event
{
    /**
     * @event event raised at the beginning of [[delete()]]. You may set
     * [[Event::isValid]] to be false to stop the validation.
     */    
    const BEFORE = 'activeRecordDelete.before';
    /**
     * @event raised after executing delete action
     */
    const AFTER = 'activeRecordDelete.after';

    /**
     * Creates BEFORE DELETE event.
     * @return self created event
     */
    public static function before(): self
    {
        return new static(static::BEFORE);
    }

    /**
     * Creates AFTER DELETE event.
     * @return self created event
     */
    public static function after(): self
    {
        return new static(static::AFTER);
    }

}
