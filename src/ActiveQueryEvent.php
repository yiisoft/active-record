<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\ActiveRecord;

use yii\base\Event;

/**
 * ActiveQueryEvent represents the event parameter used for an active queryevent.
 *
 * @author Fabrizio Caldarelli <fabrizio.caldarelli@gmail.com>
 * @since 3.0
 */
class ActiveQueryEvent extends Event
{
    /**
     * @event raised after executing init
     */
    const INIT = 'yii\base\Event\ActiveQueryEvent::INIT';

    /**
     * Creates INIT event.
     * @return self created event
     */
    public static function init(): self
    {
        return new static(static::INIT);
    }
}
