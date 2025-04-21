<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\Tests\Stubs\ArrayableActiveRecord;

/**
 * Class CustomerClosureField.
 */
class CustomerForArrayable extends ArrayableActiveRecord
{
    public array $items = [];

    public ?CustomerForArrayable $item = null;

    protected int $id;
    protected string $email;
    protected string|null $name = null;
    protected string|null $address = null;
    protected int|null $status = 0;
    protected bool|string|null $bool_status = false;
    protected int|null $profile_id = null;

    public function tableName(): string
    {
        return 'customer';
    }

    public function fields(): array
    {
        $fields = parent::fields();

        $fields['item'] = 'item';
        $fields['items'] = 'items';

        return $fields;
    }

    public function setItem(self $item)
    {
        $this->item = $item;
    }

    public function setItems(self ...$items)
    {
        $this->items = $items;
    }

    public function toArray(array $fields = [], array $expand = [], bool $recursive = true): array
    {
        $data = parent::toArray($fields, $expand, $recursive);

        $data['status'] = $this->status == 1 ? 'active' : 'inactive';

        return $data;
    }
}
