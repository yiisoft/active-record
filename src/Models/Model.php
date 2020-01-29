<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Models;

use Yiisoft\ActiveRecord\Contracts\StaticInstanceInterface;
use Yiisoft\ActiveRecord\Traits\StaticInstanceTrait;
use Yiisoft\ActiveRecord\Validators\RequiredValidator;
use Yiisoft\ActiveRecord\Validators\Validator;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\UnknownPropertyException;
use Yiisoft\Strings\Inflector;

/**
 * Model is the base class for data models.
 *
 * Model implements the following commonly used features:
 *
 * - attribute declaration: by default, every public class member is considered as
 *   a model attribute
 * - attribute labels: each attribute may be associated with a label for display purpose
 * - massive attribute assignment
 * - scenario-based validation
 *
 * Model also raises the following events when performing data validation:
 *
 * - {@see EVENT_BEFORE_VALIDATE}: an event raised at the beginning of {@see validate()}
 * - {@see EVENT_AFTER_VALIDATE}: an event raised at the end of {@see validate()}
 *
 * You may directly use Model to store model data, or extend it with customization.
 *
 * For more details and usage information on Model, see the [guide article on models](guide:structure-models).
 *
 * @property \Yiisoft\Validators\Validator[] $activeValidators The validators applicable to the current
 * {@see scenario}. This property is read-only.
 * @property array $attributes Attribute values (name => value).
 * @property array $errors An array of errors for all attributes. Empty array is returned if no error. The result is a
 * two-dimensional array. See {@see getErrors()} for detailed description. This property is read-only.
 * @property array $firstErrors The first errors. The array keys are the attribute names, and the array values are the
 * corresponding error messages. An empty array will be returned if there is no error. This property is read-only.
 * @property ArrayIterator $iterator An iterator for traversing the items in the list. This property is read-only.
 * @property string $scenario The scenario that this model is in. Defaults to {@see SCENARIO_DEFAULT}.
 * @property ArrayObject|\Yiisoft\Validators\Validator[] $validators All the validators declared in the model.
 * This property is read-only.
 */
class Model implements \IteratorAggregate, \ArrayAccess
{
    use StaticInstanceTrait;

    /**
     * The name of the default scenario.
     */
    const SCENARIO_DEFAULT = 'default';

    /**
     * @event ModelEvent an event raised at the beginning of [[validate()]]. You may set {@see ModelEvent::isValid} to
     * be false to stop the validation.
     */
    const EVENT_BEFORE_VALIDATE = 'beforeValidate';

    /**
     * @event Event an event raised at the end of [[validate()]]
     */
    const EVENT_AFTER_VALIDATE = 'afterValidate';

    /**
     * @var array validation errors (attribute name => array of errors)
     */
    private array $errors = [];

    /**
     * @var \ArrayObject list of validators
     */
    private $validators;

    /**
     * @var string current scenario
     */
    private string $scenario = self::SCENARIO_DEFAULT;

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by {@see validate()} to check if attribute values are valid.
     *
     * Child classes may override this method to declare different validation rules.
     *
     * Each rule is an array with the following structure:
     *
     * ```php
     * [
     *     ['attribute1', 'attribute2'],
     *     'validator type',
     *     'on' => ['scenario1', 'scenario2'],
     *     //...other parameters...
     * ]
     * ```
     *
     * where
     *
     * - attribute list: required, specifies the attributes array to be validated, for single attribute you can pass a
     * string;
     * - validator type: required, specifies the validator to be used. It can be a built-in validator name, a method
     * name of the model class, an anonymous function, or a validator class name.
     * - on: optional, specifies the {@see scenario|scenarios} array in which the validation rule can be applied.
     * If this option is not set, the rule will apply to all scenarios.
     * - additional name-value pairs can be specified to initialize the corresponding validator properties. Please
     * refer to individual validator class API for possible properties.
     *
     * A validator can be either an object of a class extending {@see Validator}, or a model class method (called
     * *inline validator*) that has the following signature:
     *
     * ```php
     * // $params refers to validation parameters given in the rule
     * function validatorName($attribute, $params)
     * ```
     *
     * In the above `$attribute` refers to the attribute currently being validated while `$params` contains an array of
     * validator configuration options such as `max` in case of `string` validator. The value of the attribute
     * currently being validated can be accessed as `$this->$attribute`. Note the `$` before `attribute`; this is taking
     * the value of the variable `$attribute` and using it as the name of the property to access.
     *
     * Yii also provides a set of {@see Validator::builtInValidators|built-in validators}.
     *
     * Each one has an alias name which can be used when specifying a validation rule.
     *
     * Below are some examples:
     *
     * ```php
     * [
     *     // built-in "required" validator
     *     [['username', 'password'], 'required'],
     *     // built-in "string" validator customized with "min" and "max" properties
     *     ['username', 'string', 'min' => 3, 'max' => 12],
     *     // built-in "compare" validator that is used in "register" scenario only
     *     ['password', 'compare', 'compareAttribute' => 'password2', 'on' => 'register'],
     *     // an inline validator defined via the "authenticate()" method in the model class
     *     ['password', 'authenticate', 'on' => 'login'],
     *     // a validator of class "DateRangeValidator"
     *     ['dateRange', 'DateRangeValidator'],
     * ];
     * ```
     *
     * Note, in order to inherit rules defined in the parent class, a child class needs to
     * merge the parent rules with child rules using functions such as `array_merge()`.
     *
     * @return array validation rules
     *
     * {@see scenarios()}
     */
    public function rules()
    {
        return [];
    }

    /**
     * Returns a list of scenarios and the corresponding active attributes.
     *
     * An active attribute is one that is subject to validation in the current scenario.
     *
     * The returned array should be in the following format:
     *
     * ```php
     * [
     *     'scenario1' => ['attribute11', 'attribute12', ...],
     *     'scenario2' => ['attribute21', 'attribute22', ...],
     *     ...
     * ]
     * ```
     *
     * By default, an active attribute is considered safe and can be massively assigned.
     *
     * If an attribute should NOT be massively assigned (thus considered unsafe), please prefix the attribute with an
     * exclamation character (e.g. `'!rank'`).
     *
     * The default implementation of this method will return all scenarios found in the {@see rules()} declaration. A
     * special scenario named {@see SCENARIO_DEFAULT} will contain all attributes found in the {@see rules()}. Each
     * scenario will be associated with the attributes that are being validated by the validation rules that apply to
     * the scenario.
     *
     * @return array a list of scenarios and the corresponding active attributes.
     */
    public function scenarios(): array
    {
        $scenarios = [self::SCENARIO_DEFAULT => []];

        foreach ($this->getValidators() as $validator) {
            foreach ($validator->on as $scenario) {
                $scenarios[$scenario] = [];
            }
            foreach ($validator->except as $scenario) {
                $scenarios[$scenario] = [];
            }
        }

        $names = array_keys($scenarios);

        foreach ($this->getValidators() as $validator) {
            if (empty($validator->on) && empty($validator->except)) {
                foreach ($names as $name) {
                    foreach ($validator->attributes as $attribute) {
                        $scenarios[$name][$attribute] = true;
                    }
                }
            } elseif (empty($validator->on)) {
                foreach ($names as $name) {
                    if (!in_array($name, $validator->except, true)) {
                        foreach ($validator->attributes as $attribute) {
                            $scenarios[$name][$attribute] = true;
                        }
                    }
                }
            } else {
                foreach ($validator->on as $name) {
                    foreach ($validator->attributes as $attribute) {
                        $scenarios[$name][$attribute] = true;
                    }
                }
            }
        }

        foreach ($scenarios as $scenario => $attributes) {
            if (!empty($attributes)) {
                $scenarios[$scenario] = array_keys($attributes);
            }
        }

        return $scenarios;
    }

    /**
     * Returns the form name that this model class should use.
     *
     * The form name is mainly used by {@see \Yiisoft\Yii\Widgets\ActiveForm} to determine how to name the input fields
     * for the attributes in a model. If the form name is "A" and an attribute name is "b", then the corresponding input
     * name would be "A[b]". If the form name is an empty string, then the input name would be "b".
     *
     * The purpose of the above naming schema is that for forms which contain multiple different models, the attributes
     * of each model are grouped in sub-arrays of the POST-data and it is easier to differentiate between them.
     *
     * By default, this method returns the model class name (without the namespace part) as the form name. You may
     * override it when the model is used in different forms.
     *
     * @return string the form name of this model class.
     *
     * {@see load()}
     *
     * @throws InvalidConfigException when form is defined with anonymous class and `formName()` method is not
     * overridden.
     */
    public function formName()
    {
        $reflector = new \ReflectionClass($this);

        if ($reflector->isAnonymous()) {
            throw new InvalidConfigException('The "formName()" method should be explicitly defined for anonymous models');
        }

        return $reflector->getShortName();
    }

    /**
     * Returns the list of attribute names.
     *
     * By default, this method returns all public non-static properties of the class.
     *
     * You may override this method to change the default behavior.
     *
     * @return array list of attribute names.
     */
    public function attributes(): array
    {
        $class = new \ReflectionClass($this);

        $names = [];

        foreach ($class->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if (!$property->isStatic()) {
                $names[] = $property->getName();
            }
        }

        return $names;
    }

    /**
     * Returns the attribute labels.
     *
     * Attribute labels are mainly used for display purpose. For example, given an attribute `firstName`, we can declare
     * a label `First Name` which is more user-friendly and can be displayed to end users.
     *
     * By default an attribute label is generated using {@see generateAttributeLabel()}.
     *
     * This method allows you to explicitly specify attribute labels.
     *
     * Note, in order to inherit labels defined in the parent class, a child class needs to merge the parent labels with
     * child labels using functions such as `array_merge()`.
     *
     * @return array attribute labels (name => label)
     *
     * {@see generateAttributeLabel()}
     */
    public function attributeLabels()
    {
        return [];
    }

    /**
     * Returns the attribute hints.
     *
     * Attribute hints are mainly used for display purpose. For example, given an attribute `isPublic`, we can declare a
     * hint `Whether the post should be visible for not logged in users`, which provides user-friendly description of
     * the attribute meaning and can be displayed to end users.
     *
     * Unlike label hint will not be generated, if its explicit declaration is omitted.
     *
     * Note, in order to inherit hints defined in the parent class, a child class needs to merge the parent hints with
     * child hints using functions such as `array_merge()`.
     *
     * @return array attribute hints (name => hint)
     */
    public function attributeHints()
    {
        return [];
    }

    /**
     * Performs the data validation.
     *
     * This method executes the validation rules applicable to the current {@see scenario}.
     *
     * The following criteria are used to determine whether a rule is currently applicable:
     *
     * - the rule must be associated with the attributes relevant to the current scenario;
     * - the rules must be effective for the current scenario.
     *
     * This method will call {@see beforeValidate()} and {@see afterValidate()} before and after the actual validation,
     * respectively. If [[beforeValidate()]] returns false, the validation will be cancelled and {@see afterValidate()}
     * will not be called.
     *
     * Errors found during the validation can be retrieved via {@see getErrors()}, {@see getFirstErrors()} and
     * {@see getFirstError()}.
     *
     * @param string[]|string $attributeNames attribute name or list of attribute names that should be validated.
     * If this parameter is empty, it means any attribute listed in the applicable
     * validation rules should be validated.
     * @param bool $clearErrors whether to call {@see clearErrors()} before performing validation
     * @return bool whether the validation is successful without any error.
     *
     * @throws InvalidArgumentException if the current scenario is unknown.
     */
    public function validate($attributeNames = null, $clearErrors = true)
    {
        if ($clearErrors) {
            $this->clearErrors();
        }

        if (!$this->beforeValidate()) {
            return false;
        }

        $scenarios = $this->scenarios();
        $scenario = $this->getScenario();

        if (!isset($scenarios[$scenario])) {
            throw new InvalidArgumentException("Unknown scenario: $scenario");
        }

        if ($attributeNames === null) {
            $attributeNames = $this->activeAttributes();
        }

        $attributeNames = (array)$attributeNames;

        foreach ($this->getActiveValidators() as $validator) {
            $validator->validateAttributes($this, $attributeNames);
        }

        $this->afterValidate();

        return !$this->hasErrors();
    }

    /**
     * This method is invoked before validation starts.
     *
     * The default implementation raises a `beforeValidate` event.
     * You may override this method to do preliminary checks before validation.
     * Make sure the parent implementation is invoked so that the event can be raised.
     *
     * @return bool whether the validation should be executed. Defaults to true. If false is returned, the validation
     * will stop and the model is considered invalid.
     */
    public function beforeValidate()
    {
    }

    /**
     * This method is invoked after validation ends.
     * The default implementation raises an `afterValidate` event.
     * You may override this method to do postprocessing after validation.
     * Make sure the parent implementation is invoked so that the event can be raised.
     */
    public function afterValidate()
    {
    }

    /**
     * Returns all the validators declared in {@see rules()}.
     *
     * This method differs from {@see getActiveValidators()} in that the latter only returns the validators applicable
     * to the current {@see scenario}.
     *
     * Because this method returns an ArrayObject object, you may manipulate it by inserting or removing validators
     * (useful in model behaviors).
     *
     * For example,
     *
     * ```php
     * $model->validators[] = $newValidator;
     * ```
     *
     * @return ArrayObject|\Yiisoft\Validators\Validator[] all the validators declared in the model.
     */
    public function getValidators()
    {
        if ($this->validators === null) {
            $this->validators = $this->createValidators();
        }

        return $this->validators;
    }

    /**
     * Returns the validators applicable to the current {@see scenario}.
     *
     * @param string $attribute the name of the attribute whose applicable validators should be returned.
     * If this is null, the validators for ALL attributes in the model will be returned.
     *
     * @return \Yiisoft\Validators\Validator[] the validators applicable to the current {@see scenario}.
     */
    public function getActiveValidators($attribute = null)
    {
        $activeAttributes = $this->activeAttributes();

        if ($attribute !== null && !in_array($attribute, $activeAttributes, true)) {
            return [];
        }

        $scenario = $this->getScenario();
        $validators = [];

        foreach ($this->getValidators() as $validator) {
            if ($attribute === null) {
                $validatorAttributes = $validator->getValidationAttributes($activeAttributes);
                $attributeValid = !empty($validatorAttributes);
            } else {
                $attributeValid = in_array($attribute, $validator->getValidationAttributes($attribute), true);
            }
            if ($attributeValid && $validator->isActive($scenario)) {
                $validators[] = $validator;
            }
        }

        return $validators;
    }

    /**
     * Creates validator objects based on the validation rules specified in {@see rules()}.
     *
     * Unlike {@see getValidators()}, each time this method is called, a new list of validators will be returned.
     *
     * @return \ArrayObject validators
     *
     * @throws InvalidConfigException if any validation rule configuration is invalid
     */
    public function createValidators()
    {
        $validators = new \ArrayObject();

        foreach ($this->rules() as $rule) {
            if ($rule instanceof Validator) {
                $validators->append($rule);
            } elseif (is_array($rule) && isset($rule[0], $rule[1])) { // attributes, validator type
                $validator = Validator::createValidator($rule[1], $this, (array) $rule[0], array_slice($rule, 2));
                $validators->append($validator);
            } else {
                throw new InvalidConfigException('Invalid validation rule: a rule must specify both attribute names and validator type.');
            }
        }

        return $validators;
    }

    /**
     * Returns a value indicating whether the attribute is required.
     *
     * This is determined by checking if the attribute is associated with a
     * {@see \Yiisoft\Validators\RequiredValidator|required} validation rule in the current {@see scenario}.
     *
     * Note that when the validator has a conditional validation applied using
     * {@see \Yiisoft\validators\RequiredValidator::$when|$when} this method will return `false` regardless of the `when`
     * condition because it may be called be before the model is loaded with data.
     *
     * @param string $attribute attribute name
     *
     * @return bool whether the attribute is required
     */
    public function isAttributeRequired($attribute)
    {
        foreach ($this->getActiveValidators($attribute) as $validator) {
            if ($validator instanceof RequiredValidator && $validator->when === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a value indicating whether the attribute is safe for massive assignments.
     *
     * @param string $attribute attribute name
     *
     * @return bool whether the attribute is safe for massive assignments
     *
     * {@see safeAttributes()}
     */
    public function isAttributeSafe(string $attribute): bool
    {
        return in_array($attribute, $this->safeAttributes(), true);
    }

    /**
     * Returns a value indicating whether the attribute is active in the current scenario.
     *
     * @param string $attribute attribute name
     *
     * @return bool whether the attribute is active in the current scenario
     *
     * {@see activeAttributes()}
     */
    public function isAttributeActive(string $attribute): bool
    {
        return in_array($attribute, $this->activeAttributes(), true);
    }

    /**
     * Returns the text label for the specified attribute.
     *
     * @param string $attribute the attribute name
     *
     * @return string the attribute label
     *
     * {@see generateAttributeLabel()}
     * {@see attributeLabels()}
     */
    public function getAttributeLabel(string $attribute): string
    {
        $labels = $this->attributeLabels();

        return isset($labels[$attribute]) ? $labels[$attribute] : $this->generateAttributeLabel($attribute);
    }

    /**
     * Returns the text hint for the specified attribute.
     *
     * @param string $attribute the attribute name
     *
     * @return string the attribute hint
     *
     * {@see attributeHints()}
     */
    public function getAttributeHint(string $attribute): string
    {
        $hints = $this->attributeHints();

        return isset($hints[$attribute]) ? $hints[$attribute] : '';
    }

    /**
     * Returns a value indicating whether there is any validation error.
     *
     * @param string|null $attribute attribute name. Use null to check all attributes.
     *
     * @return bool whether there is any error.
     */
    public function hasErrors(?string $attribute = null): bool
    {
        return $attribute === null ? !empty($this->errors) : isset($this->errors[$attribute]);
    }

    /**
     * Returns the errors for all attributes or a single attribute.
     *
     * @param string|null $attribute attribute name. Use null to retrieve errors for all attributes.
     * @property array An array of errors for all attributes. Empty array is returned if no error.
     * The result is a two-dimensional array. See {@see getErrors()} for detailed description.
     *
     * @return array errors for all attributes or the specified attribute. Empty array is returned if no error.
     * Note that when returning errors for all attributes, the result is a two-dimensional array, like the following:
     *
     * ```php
     * [
     *     'username' => [
     *         'Username is required.',
     *         'Username must contain only word characters.',
     *     ],
     *     'email' => [
     *         'Email address is invalid.',
     *     ]
     * ]
     * ```
     *
     * {@see getFirstErrors()}
     * {@see getFirstError()}
     */
    public function getErrors(string $attribute = null): array
    {
        if ($attribute === null) {
            return $this->errors === null ? [] : $this->errors;
        }

        return isset($this->errors[$attribute]) ? $this->errors[$attribute] : [];
    }

    /**
     * Returns the first error of every attribute in the model.
     *
     * @return array the first errors. The array keys are the attribute names, and the array values are the
     * corresponding error messages. An empty array will be returned if there is no error.
     *
     * {@see getErrors()}
     * {@see getFirstError()}
     */
    public function getFirstErrors()
    {
        if (empty($this->errors)) {
            return [];
        }

        $errors = [];

        foreach ($this->errors as $name => $es) {
            if (!empty($es)) {
                $errors[$name] = reset($es);
            }
        }

        return $errors;
    }

    /**
     * Returns the first error of the specified attribute.
     *
     * @param string $attribute attribute name.
     *
     * @return string|null the error message. Null is returned if no error.
     *
     * {@see getErrors()}
     * {@see getFirstErrors()}
     */
    public function getFirstError(string $attribute): ?string
    {
        return isset($this->errors[$attribute]) ? reset($this->errors[$attribute]) : null;
    }

    /**
     * Returns the errors for all attributes as a one-dimensional array.
     *
     * @param bool $showAllErrors boolean, if set to true every error message for each attribute will be shown otherwise
     * only the first error message for each attribute will be shown.
     *
     * @return array errors for all attributes as a one-dimensional array. Empty array is returned if no error.
     *
     * {@see getErrors()}
     * {@see getFirstErrors()}
     */
    public function getErrorSummary($showAllErrors)
    {
        $lines = [];
        $errors = $showAllErrors ? $this->getErrors() : $this->getFirstErrors();

        foreach ($errors as $es) {
            $lines = array_merge((array)$es, $lines);
        }

        return $lines;
    }

    /**
     * Adds a new error to the specified attribute.
     *
     * @param string $attribute attribute name
     * @param string $error new error message
     */
    public function addError($attribute, $error = ''): void
    {
        $this->errors[$attribute][] = $error;
    }

    /**
     * Adds a list of errors.
     *
     * @param array $items a list of errors. The array keys must be attribute names.
     * The array values should be error messages. If an attribute has multiple errors, these errors must be given in
     * terms of an array. You may use the result of {@see getErrors()} as the value for this parameter.
     */
    public function addErrors(array $items)
    {
        foreach ($items as $attribute => $errors) {
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    $this->addError($attribute, $error);
                }
            } else {
                $this->addError($attribute, $errors);
            }
        }
    }

    /**
     * Removes errors for all attributes or a single attribute.
     *
     * @param string $attribute attribute name. Use null to remove errors for all attributes.
     */
    public function clearErrors($attribute = null)
    {
        if ($attribute === null) {
            $this->errors = [];
        } else {
            unset($this->errors[$attribute]);
        }
    }

    /**
     * Generates a user friendly attribute label based on the give attribute name.
     *
     * This is done by replacing underscores, dashes and dots with blanks and changing the first letter of each word to
     * upper case.
     *
     * For example, 'department_name' or 'DepartmentName' will generate 'Department Name'.
     *
     * @param string $name the column name
     *
     * @return string the attribute label
     */
    public function generateAttributeLabel(string $name): string
    {
        $inflector = new Inflector();

        return $inflector->camel2words($name, true);
    }

    /**
     * Returns attribute values.
     * @param array $names list of attributes whose value needs to be returned.
     *
     * Defaults to null, meaning all attributes listed in {@see attributes()} will be returned. If it is an array, only
     * the attributes in the array will be returned.
     *
     * @param array $except list of attributes whose value should NOT be returned.
     *
     * @return array attribute values (name => value).
     */
    public function getAttributes($names = null, $except = [])
    {
        $values = [];

        if ($names === null) {
            $names = $this->attributes();
        }

        foreach ($names as $name) {
            $values[$name] = $this->$name;
        }

        foreach ($except as $name) {
            unset($values[$name]);
        }

        return $values;
    }

    /**
     * Sets the attribute values in a massive way.
     *
     * @param array $values attribute values (name => value) to be assigned to the model.
     * @param bool $safeOnly whether the assignments should only be done to the safe attributes.
     * A safe attribute is one that is associated with a validation rule in the current {@see scenario}.
     *
     * {@see safeAttributes()}
     * {@see attributes()}
     */
    public function setAttributes(array $values, bool $safeOnly = true): void
    {
        if (is_array($values)) {
            $attributes = array_flip($safeOnly ? $this->safeAttributes() : $this->attributes());
            foreach ($values as $name => $value) {
                if (isset($attributes[$name])) {
                    $this->$name = $value;
                } elseif ($safeOnly) {
                    $this->onUnsafeAttribute($name, $value);
                }
            }
        }
    }

    /**
     * This method is invoked when an unsafe attribute is being massively assigned.
     *
     * It does nothing otherwise.
     *
     * @param string $name the unsafe attribute name
     * @param mixed $value the attribute value
     */
    public function onUnsafeAttribute(string $name, $value)
    {
    }

    /**
     * Returns the scenario that this model is used in.
     *
     * Scenario affects how validation is performed and which attributes can be massively assigned.
     *
     * @return string the scenario that this model is in. Defaults to {@see SCENARIO_DEFAULT}.
     */
    public function getScenario(): string
    {
        return $this->scenario;
    }

    /**
     * Sets the scenario for the model.
     *
     * Note that this method does not check if the scenario exists or not.
     * The method {@see validate()} will perform this check.
     *
     * @param string $value the scenario that this model is in.
     */
    public function setScenario(string $value): void
    {
        $this->scenario = $value;
    }

    /**
     * Returns the attribute names that are safe to be massively assigned in the current scenario.
     *
     * @return string[] safe attribute names
     */
    public function safeAttributes(): array
    {
        $scenario = $this->getScenario();
        $scenarios = $this->scenarios();

        if (!isset($scenarios[$scenario])) {
            return [];
        }

        $attributes = [];

        foreach ($scenarios[$scenario] as $attribute) {
            if ($attribute[0] !== '!' && !in_array('!' . $attribute, $scenarios[$scenario])) {
                $attributes[] = $attribute;
            }
        }

        return $attributes;
    }

    /**
     * Returns the attribute names that are subject to validation in the current scenario.
     *
     * @return string[] safe attribute names
     */
    public function activeAttributes(): array
    {
        $scenario = $this->getScenario();
        $scenarios = $this->scenarios();

        if (!isset($scenarios[$scenario])) {
            return [];
        }

        $attributes = array_keys(array_flip($scenarios[$scenario]));

        foreach ($attributes as $i => $attribute) {
            if (strncmp($attribute, '!', 1) === 0) {
                $attributes[$i] = substr($attribute, 1);
            }
        }

        return $attributes;
    }

    /**
     * Populates the model with input data.
     *
     * This method provides a convenient shortcut for:
     *
     * ```php
     * if (isset($_POST['FormName'])) {
     *     $model->attributes = $_POST['FormName'];
     *     if ($model->save()) {
     *         // handle success
     *     }
     * }
     * ```
     *
     * which, with `load()` can be written as:
     *
     * ```php
     * if ($model->load($_POST) && $model->save()) {
     *     // handle success
     * }
     * ```
     *
     * `load()` gets the `'FormName'` from the model's {@see formName()} method (which you may override), unless the
     * `$formName` parameter is given. If the form name is empty, `load()` populates the model with the whole of
     * `$data`, instead of `$data['FormName']`.
     *
     * Note, that the data being populated is subject to the safety check by {@see setAttributes()}.
     *
     * @param array $data the data array to load, typically `$_POST` or `$_GET`.
     * @param string $formName the form name to use to load the data into the model. If not set, {@see formName()}
     * is used.
     *
     * @return bool whether `load()` found the expected form in `$data`.
     */
    public function load($data, $formName = null)
    {
        $scope = $formName === null ? $this->formName() : $formName;

        if ($scope === '' && !empty($data)) {
            $this->setAttributes($data);

            return true;
        } elseif (isset($data[$scope])) {
            $this->setAttributes($data[$scope]);

            return true;
        }

        return false;
    }

    /**
     * Populates a set of models with the data from end user.
     *
     * This method is mainly used to collect tabular data input.
     * The data to be loaded for each model is `$data[formName][index]`, where `formName` refers to the value of
     * {@see formName()}, and `index` the index of the model in the `$models` array.
     * If {@see formName()} is empty, `$data[index]` will be used to populate each model.
     * The data being populated to each model is subject to the safety check by {@see setAttributes()}.
     *
     * @param array $models the models to be populated. Note that all models should have the same class.
     * @param array $data the data array. This is usually `$_POST` or `$_GET`, but can also be any valid array supplied
     * by end user.
     * @param string $formName the form name to be used for loading the data into the models. If not set, it will use
     * the {@see formName()} value of the first model in `$models`.
     *
     * @return bool whether at least one of the models is successfully populated.
     */
    public static function loadMultiple(array $models, array $data, ?string $formName = null): bool
    {
        if ($formName === null) {
            /** @var $first Model|false */
            $first = reset($models);

            if ($first === false) {
                return false;
            }

            $formName = $first->formName();
        }

        $success = false;

        foreach ($models as $i => $model) {
            /** @var $model Model */
            if ($formName == '') {
                if (!empty($data[$i]) && $model->load($data[$i], '')) {
                    $success = true;
                }
            } elseif (!empty($data[$formName][$i]) && $model->load($data[$formName][$i], '')) {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * Validates multiple models.
     *
     * This method will validate every model. The models being validated may be of the same or different types.
     *
     * @param array $models the models to be validated
     * @param array $attributeNames list of attribute names that should be validated. If this parameter is empty, it
     * means any attribute listed in the applicable validation rules should be validated.
     *
     * @return bool whether all models are valid. False will be returned if one or multiple models have validation
     * error.
     */
    public static function validateMultiple(array $models, array $attributeNames = null): bool
    {
        $valid = true;

        /** @var $model Model */
        foreach ($models as $model) {
            $valid = $model->validate($attributeNames) && $valid;
        }

        return $valid;
    }

    /**
     * Returns the list of fields that should be returned by default by {@see toArray()} when no specific fields are
     * specified.
     *
     * A field is a named element in the returned array by {toArray()}.
     *
     * This method should return an array of field names or field definitions.
     *
     * If the former, the field name will be treated as an object property name whose value will be used as the field
     * value. If the latter, the array key should be the field name while the array value should be the corresponding
     * field definition which can be either an object property name or a PHP callable returning the corresponding
     * field value. The signature of the callable should be:
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
     * - `firstName` and `lastName`: the field names are `firstName` and `lastName`, and their values are obtained from
     * the `first_name` and `last_name` properties;
     * - `fullName`: the field name is `fullName`. Its value is obtained by concatenating `first_name` and `last_name`.
     *
     * ```php
     * return [
     *     'email',
     *     'firstName' => 'first_name',
     *     'lastName' => 'last_name',
     *     'fullName' => function ($model) {
     *         return $model->first_name . ' ' . $model->last_name;
     *     },
     * ];
     * ```
     *
     * In this method, you may also want to return different lists of fields based on some context information. For
     * example, depending on {@see scenario} or the privilege of the current application user, you may return different
     * sets of visible fields or filter out some fields.
     *
     * The default implementation of this method returns {@see attributes()} indexed by the same attribute names.
     *
     * @return array the list of field names or field definitions.
     *
     * @see toArray()
     */
    public function fields(): array
    {
        $fields = $this->attributes();

        return array_combine($fields, $fields);
    }

    /**
     * Returns an iterator for traversing the attributes in the model.
     *
     * This method is required by the interface {@see \IteratorAggregate}.
     *
     * @return ArrayIterator an iterator for traversing the items in the list.
     */
    public function getIterator(): \ArrayIterator
    {
        $attributes = $this->getAttributes();

        return new \ArrayIterator($attributes);
    }

    /**
     * Returns whether there is an element at the specified offset.
     *
     * This method is required by the SPL interface {@see \ArrayAccess}.
     * It is implicitly called when you use something like `isset($model[$offset])`.
     *
     * @param mixed $offset the offset to check on.
     *
     * @return bool whether or not an offset exists.
     */
    public function offsetExists($offset): bool
    {
        return isset($this->$offset);
    }

    /**
     * Returns the element at the specified offset.
     *
     * This method is required by the SPL interface {@see \ArrayAccess}.
     * It is implicitly called when you use something like `$value = $model[$offset];`.
     *
     * @param mixed $offset the offset to retrieve element.
     *
     * @return mixed the element at the offset, null if no element is found at the offset
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    /**
     * Sets the element at the specified offset.
     *
     * This method is required by the SPL interface {@see \ArrayAccess}.
     * It is implicitly called when you use something like `$model[$offset] = $item;`.
     *
     * @param int $offset the offset to set element
     *
     * @param mixed $item the element value
     */
    public function offsetSet($offset, $item): void
    {
        $this->$offset = $item;
    }

    /**
     * Sets the element value at the specified offset to null.
     *
     * This method is required by the SPL interface {@see \ArrayAccess}.
     * It is implicitly called when you use something like `unset($model[$offset])`.
     *
     * @param mixed $offset the offset to unset element
     */
    public function offsetUnset($offset): void
    {
        $this->$offset = null;
    }

    /**
     * Returns a value indicating whether a method is defined.
     *
     * A method is defined if:
     *
     * - the class has a method with the specified name
     * - an attached behavior has a method with the given name (when `$checkBehaviors` is true).
     *
     * @param string $name the property name
     * @param bool $checkBehaviors whether to treat behaviors' methods as methods of this component
     *
     * @return bool whether the method is defined
     */
    public function hasMethod(string $name, bool $checkBehaviors = true): bool
    {
        if (method_exists($this, $name)) {
            return true;
        }

        return false;
    }

    /**
     * Returns the value of a component property.
     *
     * This method will check in the following order and act accordingly:
     *
     *  - a property defined by a getter: return the getter result
     *  - a property of a behavior: return the behavior property value
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$value = $component->property;`.
     *
     * @param string $name the property name
     * @return mixed the property value or the value of a behavior's property
     *
     * @throws UnknownPropertyException if the property is not defined
     * @throws InvalidCallException if the property is write-only.
     *
     * {@see __set()}
     */
    public function __get($name)
    {
        $getter = 'get' . $name;

        if (method_exists($this, $getter)) {
            // read property, e.g. getName()
            return $this->$getter();
        }

        if (method_exists($this, 'set' . $name)) {
            throw new InvalidCallException('Getting write-only property: ' . get_class($this) . '::' . $name);
        }

        throw new UnknownPropertyException('Getting unknown property: ' . get_class($this) . '::' . $name);
    }

    /**
     * Sets the value of a component property.
     *
     * This method will check in the following order and act accordingly:
     *
     *  - a property defined by a setter: set the property value
     *  - an event in the format of "on xyz": attach the handler to the event "xyz"
     *  - a behavior in the format of "as xyz": attach the behavior named as "xyz"
     *  - a property of a behavior: set the behavior property value
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$component->property = $value;`.
     * @param string $name the property name or the event name
     * @param mixed $value the property value
     * @throws UnknownPropertyException if the property is not defined
     * @throws InvalidCallException if the property is read-only.
     * @see __get()
     */
    public function __set($name, $value)
    {
        $setter = 'set' . $name;

        if (method_exists($this, $setter)) {
            // set property
            $this->$setter($value);

            return;
        } elseif (strncmp($name, 'on ', 3) === 0) {
            // on event: attach event handler
            $this->on(trim(substr($name, 3)), $value);

            return;
        }
    }

    /**
     * Returns a value indicating whether a property can be read.
     *
     * A property can be read if:
     *
     * - the class has a getter method associated with the specified name
     *   (in this case, property name is case-insensitive);
     * - the class has a member variable with the specified name (when `$checkVars` is true);
     * - an attached behavior has a readable property of the given name (when `$checkBehaviors` is true).
     *
     * @param string $name the property name
     * @param bool $checkVars whether to treat member variables as properties
     *
     * @return bool whether the property can be read
     *
     * {@see canSetProperty()}
     */
    public function canGetProperty(string $name, bool $checkVars = true): bool
    {
        if (method_exists($this, 'get' . $name) || $checkVars && property_exists($this, $name)) {
            return true;
        }

        return false;
    }

    /**
     * Returns a value indicating whether a property can be set.
     *
     * A property can be written if:
     *
     * - the class has a setter method associated with the specified name
     *   (in this case, property name is case-insensitive);
     * - the class has a member variable with the specified name (when `$checkVars` is true);
     * - an attached behavior has a writable property of the given name (when `$checkBehaviors` is true).
     *
     * @param string $name the property name
     * @param bool $checkVars whether to treat member variables as properties
     *
     * @return bool whether the property can be written
     *
     * {@see canGetProperty()}
     */
    public function canSetProperty(string $name, bool $checkVars = true): bool
    {
        if (method_exists($this, 'set' . $name) || $checkVars && property_exists($this, $name)) {
            return true;
        }

        return false;
    }
}
