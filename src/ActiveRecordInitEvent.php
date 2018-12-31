<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\activerecord;

use yii\base\Event;


/**
 * ActiveRecordInitEvent represents the event parameter used for an active record init event.
 *
 * @author Fabrizio Caldarelli <fabrizio.caldarelli@gmail.com>
 * @since 3.0
 */
class ActiveRecordInitEvent extends Event
{
    /**
     * @event raised after executing find action
     */
    const INIT = 'activeRecordInit.init';

    /**
     * Creates INIT event.
     * @return self created event
     */
    public static function init(): self
    {
        return new static(static::INIT);
    }


}
