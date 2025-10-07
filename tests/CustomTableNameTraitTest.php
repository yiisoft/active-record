<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CategoryCustomTableNameModel;

abstract class CustomTableNameTraitTest extends TestCase
{
    public function testDefaultTableName(): void
    {
        $model = new CategoryCustomTableNameModel();

        $this->assertSame('{{%category_custom_table_name_model}}', $model->tableName());
    }

    public function testWithTableName(): void
    {
        $baseModel = new CategoryCustomTableNameModel();
        $model = $baseModel->withTableName('category');

        $this->assertSame('{{%category_custom_table_name_model}}', $baseModel->tableName());
        $this->assertSame('category', $model->tableName());
        $this->assertNotSame($baseModel, $model);
    }
}
