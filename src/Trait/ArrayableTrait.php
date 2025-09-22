<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Closure;
use Yiisoft\ActiveRecord\AbstractActiveRecord;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\Arrays\ArrayableInterface;

use function array_combine;
use function array_keys;

/**
 * Trait to implement {@see \Yiisoft\Arrays\ArrayableInterface} interface for ActiveRecord.
 *
 * @method string[] propertyNames()
 * @see ActiveRecordInterface::propertyNames()
 *
 * @method array relatedRecords()
 * @see AbstractActiveRecord::relatedRecords()
 *
 * @psalm-import-type FieldsArray from ArrayableInterface
 */
trait ArrayableTrait
{
    use \Yiisoft\Arrays\ArrayableTrait;

    /**
     * Returns the list of fields that can be expanded further and returned by {@see toArray()}.
     *
     * This method is similar to {@see fields()} except that the list of fields returned by this method are not returned
     * by default by {@see toArray()}. Only when field names to be expanded are explicitly specified when calling
     * {@see toArray()}, will their values be exported.
     *
     * The default implementation returns the names of the relations defined in this `ActiveRecord` class indexed by
     * themselves.
     *
     * You may override this method to return a list of expandable fields.
     *
     * @return (Closure|string)[] The list of expandable field names or field definitions. Please refer
     * to {@see fields()} on the format of the return value.
     *
     * @psalm-return FieldsArray
     */
    public function extraFields(): array
    {
        $fields = array_keys($this->relatedRecords());

        return array_combine($fields, $fields);
    }

    /**
     * Returns the list of fields that should be returned by default by {@see toArray()}.
     *
     * A field is a named element in the returned array by {@see toArray()}.
     *
     * This method should return an array of field names or field definitions.
     * If the former, the field name will be treated as an object property name whose value will be used
     * as the field value. If the latter, the array key should be the field name while the array value should be
     * the corresponding field definition, which can be either an object property name or a PHP callable
     * returning the corresponding field value. The signature of the callable should be:
     *
     * ```php
     * function ($model, $field) {
     *     // return field value
     * }
     * ```
     *
     * For example, the following code declares four fields:
     *
     * - `email`: the field name is the same as the property name `email`;
     * - `firstName` and `lastName`: the field names are `firstName` and `lastName`, and their
     *   values are obtained from the `first_name` and `last_name` properties;
     * - `fullName`: the field name is `fullName`. Its value is obtained by concatenating `first_name`
     *   and `last_name`.
     *
     * ```php
     * return [
     *     'email',
     *     'firstName' => 'first_name',
     *     'lastName' => 'last_name',
     *     'fullName' => function () {
     *         return $this->first_name . ' ' . $this->last_name;
     *     },
     * ];
     * ```
     *
     * In this method, you may also want to return different lists of fields based on some context
     * information. For example, depending on the privilege of the current application user,
     * you may return different sets of visible fields or filter out some fields.
     *
     * The default implementation returns the names of the properties of this record indexed by themselves.
     *
     * @return (Closure|string)[] The list of field names or field definitions.
     *
     * @psalm-return FieldsArray
     */
    public function fields(): array
    {
        $fields = $this->propertyNames();

        return array_combine($fields, $fields);
    }
}
