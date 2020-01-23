<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\ActiveRecord;

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
     * Creates BEFORE INSERT event.
     * @return self created event
     */
    public static function beforeInsert(): self
    {
        return new static(static::BEFORE_INSERT, true);
    }

    /**
     * Creates AFTER INSERT event.
     * @param string $changedAttributes list of changed attributes
     * @return self created event
     */
    public static function afterInsert(array $changedAttributes = null): self
    {
        return new static(static::AFTER_INSERT, true, $changedAttributes);
    }

    /**
     * Creates BEFORE UPDATE event.
     * @return self created event
     */
    public static function beforeUpdate(): self
    {
        return new static(static::BEFORE_UPDATE, false);
    }

    /**
     * Creates AFTER UPDATE event.
     * @param string $changedAttributes list of changed attributes
     * @return self created event
     */
    public static function afterUpdate(array $changedAttributes = null): self
    {
        return new static(static::AFTER_UPDATE, false, $changedAttributes);
    }

    public function getInsert(): bool
    {
        return $this->_insert;
    }

    public function getChangedAttributes(): array
    {
        return $this->_changedAttributes;
    }
}
