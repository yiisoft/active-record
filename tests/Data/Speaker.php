<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Data;

use Yiisoft\ActiveRecord\Models\Model;

/**
 * Speaker.
 */
class Speaker extends Model
{
    public ?string $firstName = null;
    public ?string $lastName = null;
    public ?string $customLabel = null;
    public ?string $underscore_style = null;

    protected ?string $protectedProperty = null;
    private ?string $privateProperty = null;

    public static $formName = 'Speaker';

    public function formName()
    {
        return static::$formName;
    }

    public function attributeLabels(): array
    {
        return [
            'customLabel' => 'This is the custom label',
        ];
    }

    public function scenarios(): array
    {
        return [
            'test' => ['firstName', 'lastName', '!underscore_style'],
            'duplicates' => ['firstName', 'firstName', '!underscore_style', '!underscore_style'],
        ];
    }
}
