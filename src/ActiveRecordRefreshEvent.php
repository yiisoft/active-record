<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\activerecord;

use yii\base\Event;


/**
 * ActiveRecordRefreshEvent represents the event parameter used for an active record refresh event.
 *
 * @author Fabrizio Caldarelli <fabrizio.caldarelli@gmail.com>
 * @since 3.0
 */
class ActiveRecordRefreshEvent extends Event
{
    /**
     * @event raised after executing refresh action
     */
    const AFTER = 'activeRecordRefresh.after';

    /**
     * Creates AFTER REFRESH event.
     * @return self created event
     */
    public static function after(): self
    {
        return new static(static::AFTER);
    }

}
