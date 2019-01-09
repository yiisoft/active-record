<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\activerecord;

use yii\base\Event;


/**
 * ActiveRecordSaveEvent represents the event parameter used for an active record save event.
 *
 * @author Fabrizio Caldarelli <fabrizio.caldarelli@gmail.com>
 * @since 3.0
 */
class ActiveRecordSaveEvent extends Event
{
    /**
     * @event event raised at the beginning of [[save()]]. You may set
     * [[Event::isValid]] to be false to stop the validation.
     */    
    const BEFORE_INSERT = 'yii\base\Event\ActiveRecordSaveEvent::BEFORE_INSERT';
    /**
     * @event raised after executing insert action
     */
    const AFTER_INSERT = 'yii\base\Event\ActiveRecordSaveEvent::AFTER_INSERT';

    /**
     * @event event raised at the beginning of [[save()]]. You may set
     * [[Event::isValid]] to be false to stop the validation.
     */    
    const BEFORE_UPDATE = 'yii\base\Event\ActiveRecordSaveEvent::BEFORE_UPDATE';
    /**
     * @event raised after executing update action
     */
    const AFTER_UPDATE = 'yii\base\Event\ActiveRecordSaveEvent::AFTER_UPDATE';    

    /**
     * @var bool insert specify if action is insert or update ( true for insert, false for update )
     */
    private $_insert;

    /**
     * @var array list of changed attributes
     */
    private $_changedAttributes;


    /**
     * @param string $name event name
     * @param string $insert specify if action is insert or update ( true for insert, false for update )
     * @param string $changedAttributes list of changed attributes
     */
    public function __construct(string $name, bool $insert, array $changedAttributes = null)
    {
        parent::__construct($name);

        $this->_insert = $insert;
        $this->_changedAttributes = $changedAttributes;
    }

    /**
     * Creates BEFORE SAVE event.
     * @param string $insert specify if action is insert or update ( true for insert, false for update )
     * @return self created event
     */
    public static function before(bool $insert): self
    {
        return new static($insert ? static::BEFORE_INSERT : static::BEFORE_UPDATE, $insert);
    }

    /**
     * Creates AFTER SAVE event.
     * @param string $insert specify if action is insert or update ( true for insert, false for update )
     * @param string $changedAttributes list of changed attributes
     * @return self created event
     */
    public static function after(bool $insert,array $changedAttributes = null): self
    {
        return new static($insert ? static::AFTER_INSERT : static::AFTER_UPDATE, $insert, $changedAttributes);
    }

    public function getInsert() : bool 
    { 
        return $this->_insert; 
    }
    public function getChangedAttributes() : array 
    { 
        return $this->_changedAttributes; 
    }

}
