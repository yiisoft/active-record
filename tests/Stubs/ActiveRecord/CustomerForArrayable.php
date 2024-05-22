<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord;

use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Class CustomerClosureField.
 */
class CustomerForArrayable extends ActiveRecord
{
    public array $items = [];

    public ?CustomerForArrayable $item = null;

    protected int $id;
    protected string $email;
    protected string|null $name;
    protected string|null $address;
    protected int|null $status = 0;
    protected bool|string|null $bool_status = false;
    protected int|null $profile_id;

    public function getTableName(): string
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
