<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\activerecord;

use yii\base\Event;


/**
 * ActiveRecordFindEvent represents the event parameter used for an active record find event.
 *
 * @author Fabrizio Caldarelli <fabrizio.caldarelli@gmail.com>
 * @since 3.0
 */
class ActiveRecordFindEvent extends Event
{
    /**
     * @event raised after executing find action
     */
    const AFTER = 'activeRecordFind.after';

    /**
     * Creates BEFORE FIND event.
     * @return self created event
     */
    public static function before(): self
    {
        return new static(static::BEFORE);
    }

    /**
     * Creates AFTER FIND event.
     * @return self created event
     */
    public static function after(): self
    {
        return new static(static::AFTER);
    }

}
