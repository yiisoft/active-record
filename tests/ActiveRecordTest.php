<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use DivisionByZeroError;
use ReflectionException;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Animal;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Cat;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerClosureField;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerForArrayable;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerWithAlias;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Dog;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\NoExist;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\NullValues;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderItem;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderItemWithNullFK;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Type;
use Yiisoft\ActiveRecord\Tests\Support\Assert;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\UnknownPropertyException;
use Yiisoft\Db\Query\Query;

abstract class ActiveRecordTest extends TestCase
{
    public function testStoreNull(): void
    {
        $this->checkFixture($this->db, 'null_values', true);

        $record = new NullValues($this->db);

        $this->assertNull($record->getAttribute('var1'));
        $this->assertNull($record->getAttribute('var2'));
        $this->assertNull($record->getAttribute('var3'));
        $this->assertNull($record->getAttribute('stringcol'));

        $record->setAttribute('var1', 123);
        $record->setAttribute('var2', 456);
        $record->setAttribute('var3', 789);
        $record->setAttribute('stringcol', 'hello!');
        $record->save();

        $this->assertTrue($record->refresh());
        $this->assertEquals(123, $record->getAttribute('var1'));
        $this->assertEquals(456, $record->getAttribute('var2'));
        $this->assertEquals(789, $record->getAttribute('var3'));
        $this->assertEquals('hello!', $record->getAttribute('stringcol'));

        $record->setAttribute('var1', null);
        $record->setAttribute('var2', null);
        $record->setAttribute('var3', null);
        $record->setAttribute('stringcol', null);
        $record->save();

        $this->assertTrue($record->refresh());
        $this->assertNull($record->getAttribute('var1'));
        $this->assertNull($record->getAttribute('var2'));
        $this->assertNull($record->getAttribute('var3'));
        $this->assertNull($record->getAttribute('>stringcol'));

        $record->setAttribute('var1', 0);
        $record->setAttribute('var2', 0);
        $record->setAttribute('var3', 0);
        $record->setAttribute('stringcol', '');
        $record->save();

        $this->assertTrue($record->refresh());
        $this->assertEquals(0, $record->getAttribute('var1'));
        $this->assertEquals(0, $record->getAttribute('var2'));
        $this->assertEquals(0, $record->getAttribute('var3'));
        $this->assertEquals('', $record->getAttribute('stringcol'));
    }

    public function testStoreEmpty(): void
    {
        $this->checkFixture($this->db, 'null_values');

        $record = new NullValues($this->db);

        /** this is to simulate empty html form submission */
        $record->var1 = '';
        $record->var2 = '';
        $record->var3 = '';
        $record->stringcol = '';
        $record->save();

        $this->assertTrue($record->refresh());

        /** {@see https://github.com/yiisoft/yii2/commit/34945b0b69011bc7cab684c7f7095d837892a0d4#commitcomment-4458225} */
        $this->assertSame($record->var1, $record->var2);
        $this->assertSame($record->var2, $record->var3);
    }

    public function testIsPrimaryKey(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new Customer($this->db);
        $orderItem = new OrderItem($this->db);

        $this->assertTrue($customer->isPrimaryKey(['id']));
        $this->assertFalse($customer->isPrimaryKey([]));
        $this->assertFalse($customer->isPrimaryKey(['id', 'name']));
        $this->assertFalse($customer->isPrimaryKey(['name']));
        $this->assertFalse($customer->isPrimaryKey(['name', 'email']));

        $this->assertTrue($orderItem->isPrimaryKey(['order_id', 'item_id']));
        $this->assertFalse($orderItem->isPrimaryKey([]));
        $this->assertFalse($orderItem->isPrimaryKey(['order_id']));
        $this->assertFalse($orderItem->isPrimaryKey(['item_id']));
        $this->assertFalse($orderItem->isPrimaryKey(['quantity']));
        $this->assertFalse($orderItem->isPrimaryKey(['quantity', 'subtotal']));
        $this->assertFalse($orderItem->isPrimaryKey(['order_id', 'item_id', 'quantity']));
    }

    public function testOutdatedRelationsAreResetForNewRecords(): void
    {
        $this->checkFixture($this->db, 'order_item');

        $orderItem = new OrderItem($this->db);

        $orderItem->order_id = 1;
        $orderItem->item_id = 3;
        $this->assertEquals(1, $orderItem->order->id);
        $this->assertEquals(3, $orderItem->item->id);

        /** test `__set()`. */
        $orderItem->order_id = 2;
        $orderItem->item_id = 1;
        $this->assertEquals(2, $orderItem->order->id);
        $this->assertEquals(1, $orderItem->item->id);

        /** test `setAttribute()`. */
        $orderItem->setAttribute('order_id', 2);
        $orderItem->setAttribute('item_id', 2);
        $this->assertEquals(2, $orderItem->order->id);
        $this->assertEquals(2, $orderItem->item->id);
    }

    public function testDefaultValues(): void
    {
        $this->checkFixture($this->db, 'type');

        $arClass = new Type($this->db);

        $arClass->loadDefaultValues();

        $this->assertEquals(1, $arClass->int_col2);
        $this->assertEquals('something', $arClass->char_col2);
        $this->assertEquals(1.23, $arClass->float_col2);
        $this->assertEquals(33.22, $arClass->numeric_col);
        $this->assertEquals(true, $arClass->bool_col2);
        $this->assertEquals('2002-01-01 00:00:00', $arClass->time);

        $arClass = new Type($this->db);
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues();
        $this->assertEquals('not something', $arClass->char_col2);

        $arClass = new Type($this->db);
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues(false);
        $this->assertEquals('something', $arClass->char_col2);
    }

    public function testCastValues(): void
    {
        $this->checkFixture($this->db, 'type');

        $arClass = new Type($this->db);

        $arClass->int_col = 123;
        $arClass->int_col2 = 456;
        $arClass->smallint_col = 42;
        $arClass->char_col = '1337';
        $arClass->char_col2 = 'test';
        $arClass->char_col3 = 'test123';
        $arClass->float_col = 3.742;
        $arClass->float_col2 = 42.1337;
        $arClass->bool_col = true;
        $arClass->bool_col2 = false;

        $arClass->save();

        /** @var $model Type */
        $aqClass = new ActiveQuery(Type::class, $this->db);
        $query = $aqClass->onePopulate();

        $this->assertSame(123, $query->int_col);
        $this->assertSame(456, $query->int_col2);
        $this->assertSame(42, $query->smallint_col);
        $this->assertSame('1337', trim($query->char_col));
        $this->assertSame('test', $query->char_col2);
        $this->assertSame('test123', $query->char_col3);
    }

    public function testPopulateRecordCallWhenQueryingOnParentClass(): void
    {
        $this->checkFixture($this->db, 'cat');

        $cat = new Cat($this->db);
        $cat->save();

        $dog = new Dog($this->db);
        $dog->save();

        $animal = new ActiveQuery(Animal::class, $this->db);

        $animals = $animal->where(['type' => Dog::class])->onePopulate();
        $this->assertEquals('bark', $animals->getDoes());

        $animals = $animal->where(['type' => Cat::class])->onePopulate();
        $this->assertEquals('meow', $animals->getDoes());
    }

    public function testSaveEmpty(): void
    {
        $this->checkFixture($this->db, 'null_values', true);

        $record = new NullValues($this->db);

        $this->assertTrue($record->save());
        $this->assertEquals(1, $record->id);
    }

    /**
     * Verify that {{}} are not going to be replaced in parameters.
     */
    public function testNoTablenameReplacement(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new Customer($this->db);

        $customer->name = 'Some {{weird}} name';
        $customer->email = 'test@example.com';
        $customer->address = 'Some {{%weird}} address';
        $customer->insert();
        $customer->refresh();

        $this->assertEquals('Some {{weird}} name', $customer->name);
        $this->assertEquals('Some {{%weird}} address', $customer->address);

        $customer->name = 'Some {{updated}} name';
        $customer->address = 'Some {{%updated}} address';
        $customer->update();

        $this->assertEquals('Some {{updated}} name', $customer->name);
        $this->assertEquals('Some {{%updated}} address', $customer->address);
    }

    public static function legalValuesForFindByCondition(): array
    {
        return [
            [Customer::class, ['id' => 1]],
            [Customer::class, ['customer.id' => 1]],
            [Customer::class, ['[[id]]' => 1]],
            [Customer::class, ['{{customer}}.[[id]]' => 1]],
            [Customer::class, ['{{%customer}}.[[id]]' => 1]],
            [CustomerWithAlias::class, ['id' => 1]],
            [CustomerWithAlias::class, ['customer.id' => 1]],
            [CustomerWithAlias::class, ['[[id]]' => 1]],
            [CustomerWithAlias::class, ['{{customer}}.[[id]]' => 1]],
            [CustomerWithAlias::class, ['{{%customer}}.[[id]]' => 1]],
            [CustomerWithAlias::class, ['csr.id' => 1], 'csr'],
            [CustomerWithAlias::class, ['{{csr}}.[[id]]' => 1], 'csr'],
        ];
    }

    /**
     * @dataProvider legalValuesForFindByCondition
     *
     * @throws ReflectionException
     */
    public function testLegalValuesForFindByCondition(
        string $modelClassName,
        array $validFilter,
        ?string $alias = null
    ): void {
        $this->checkFixture($this->db, 'customer');

        $activeQuery = new ActiveQuery($modelClassName, $this->db);

        if ($alias !== null) {
            $activeQuery->alias('csr');
        }

        /** @var Query $query */
        $query = Assert::invokeMethod($activeQuery, 'findByCondition', [$validFilter]);


        $this->db->getQueryBuilder()->build($query);

        $this->assertTrue(true);
    }

    public static function illegalValuesForFindByCondition(): array
    {
        return [
            [Customer::class, [['`id`=`id` and 1' => 1]]],
            [Customer::class, [[
                'legal' => 1,
                '`id`=`id` and 1' => 1,
            ]]],
            [Customer::class, [[
                'nested_illegal' => [
                    'false or 1=' => 1,
                ],
            ]]],
            [Customer::class, [['true--' => 1]]],

            [CustomerWithAlias::class, [['`csr`.`id`=`csr`.`id` and 1' => 1]]],
            [CustomerWithAlias::class, [[
                'legal' => 1,
                '`csr`.`id`=`csr`.`id` and 1' => 1,
            ]]],
            [CustomerWithAlias::class, [[
                'nested_illegal' => [
                    'false or 1=' => 1,
                ],
            ]]],
            [CustomerWithAlias::class, [['true--' => 1]]],
        ];
    }

    /**
     * @dataProvider illegalValuesForFindByCondition
     *
     * @throws ReflectionException
     */
    public function testValueEscapingInFindByCondition(string $modelClassName, array $filterWithInjection): void
    {
        $this->checkFixture($this->db, 'customer');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/^Key "(.+)?" is not a column name and can not be used as a filter$/'
        );

        $query = new ActiveQuery($modelClassName, $this->db);

        /** @var Query $query */
        $query = Assert::invokeMethod($query, 'findByCondition', $filterWithInjection);

        $this->db->getQueryBuilder()->build($query);
    }

    public function testRefreshQuerySetAliasFindRecord(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new CustomerWithAlias($this->db);

        $customer->id = 1;
        $customer->refresh();

        $this->assertEquals(1, $customer->id);
    }

    public function testResetNotSavedRelation(): void
    {
        $this->checkFixture($this->db, 'order');

        $order = new Order($this->db);

        $order->customer_id = 1;
        $order->created_at = 1_325_502_201;
        $order->total = 0;

        $orderItem = new OrderItem($this->db);

        $order->orderItems;

        $order->populateRelation('orderItems', [$orderItem]);

        $order->save();

        $this->assertCount(1, $order->orderItems);
    }

    public function testIssetException(): void
    {
        $this->checkFixture($this->db, 'cat');

        $cat = new Cat($this->db);

        $this->expectException(Exception::class);
        isset($cat->exception);
    }

    public function testIssetThrowable(): void
    {
        $this->checkFixture($this->db, 'cat');

        $cat = new Cat($this->db);

        $this->expectException(DivisionByZeroError::class);
        isset($cat->throwable);
    }

    public function testIssetNonExisting(): void
    {
        $this->checkFixture($this->db, 'cat');

        $cat = new Cat($this->db);

        $this->assertFalse(isset($cat->non_existing));
        $this->assertFalse(isset($cat->non_existing_property));
    }

    public function testSetAttributes(): void
    {
        $attributes = [];
        $this->checkFixture($this->db, 'customer');

        $attributes['email'] = 'samdark@mail.ru';
        $attributes['name'] = 'samdark';
        $attributes['address'] = 'rusia';
        $attributes['status'] = 1;

        if ($this->db->getDriverName() === 'pgsql') {
            $attributes['bool_status'] = true;
        }

        $attributes['profile_id'] = null;

        $customer = new Customer($this->db);

        $customer->setAttributes($attributes);

        $this->assertTrue($customer->save());
    }

    public function testSetAttributeNoExist(): void
    {
        $this->checkFixture($this->db, 'cat');

        $cat = new Cat($this->db);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Cat has no attribute named "noExist"'
        );

        $cat->setAttribute('noExist', 1);
    }

    public function testSetOldAttribute(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new Customer($this->db);

        $this->assertEmpty($customer->getOldAttribute('name'));

        $customer->setOldAttribute('name', 'samdark');

        $this->assertEquals('samdark', $customer->getOldAttribute('name'));
    }

    public function testSetOldAttributeException(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new Customer($this->db);

        $this->assertEmpty($customer->getOldAttribute('name'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer has no attribute named "noExist"'
        );
        $customer->setOldAttribute('noExist', 'samdark');
    }

    public function testIsAttributeChangedNotChanged(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new Customer($this->db);

        $this->assertEmpty($customer->getAttribute('name'));
        $this->assertEmpty($customer->getOldAttribute('name'));
        $this->assertFalse($customer->isAttributeChanged('name', false));
    }

    public function testTableSchemaException(): void
    {
        $noExist = new NoExist($this->db);

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The table does not exist: NoExist');
        $noExist->getTableSchema();
    }

    public function testInsert(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new Customer($this->db);

        $customer->email = 'user4@example.com';
        $customer->name = 'user4';
        $customer->address = 'address4';

        $this->assertNull($customer->id);
        $this->assertTrue($customer->isNewRecord);

        $customer->save();

        $this->assertNotNull($customer->id);
        $this->assertFalse($customer->isNewRecord);
    }

    /**
     * Some PDO implementations (e.g. cubrid) do not support boolean values.
     *
     * Make sure this does not affect AR layer.
     */
    public function testBooleanAttribute(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        $customer = new Customer($this->db);

        $customer->name = 'boolean customer';
        $customer->email = 'mail@example.com';
        $customer->status = true;

        $customer->save();
        $customer->refresh();
        $this->assertEquals(1, $customer->status);

        $customer->status = false;
        $customer->save();

        $customer->refresh();
        $this->assertEquals(0, $customer->status);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customers = $customerQuery->where(['status' => true])->all();
        $this->assertCount(2, $customers);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customers = $customerQuery->where(['status' => false])->all();
        $this->assertCount(1, $customers);
    }

    public function testAttributeAccess(): void
    {
        $this->checkFixture($this->db, 'customer');

        $arClass = new Customer($this->db);

        $this->assertTrue($arClass->canSetProperty('name'));
        $this->assertTrue($arClass->canGetProperty('name'));
        $this->assertFalse($arClass->canSetProperty('unExistingColumn'));
        $this->assertFalse(isset($arClass->name));

        $arClass->name = 'foo';
        $this->assertTrue(isset($arClass->name));

        unset($arClass->name);
        $this->assertNull($arClass->name);

        /** {@see https://github.com/yiisoft/yii2-gii/issues/190} */
        $baseModel = new Customer($this->db);
        $this->assertFalse($baseModel->hasProperty('unExistingColumn'));

        $customer = new Customer($this->db);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertTrue($customer->canGetProperty('id'));
        $this->assertTrue($customer->canSetProperty('id'));

        /** tests that we really can get and set this property */
        $this->assertNull($customer->id);
        $customer->id = 10;
        $this->assertNotNull($customer->id);

        /** Let's test relations */
        $this->assertTrue($customer->canGetProperty('orderItems'));
        $this->assertFalse($customer->canSetProperty('orderItems'));

        /** Newly created model must have empty relation */
        $this->assertSame([], $customer->orderItems);

        /** does it still work after accessing the relation? */
        $this->assertTrue($customer->canGetProperty('orderItems'));
        $this->assertFalse($customer->canSetProperty('orderItems'));

        try {
            /** @var $itemClass ActiveRecordInterface */
            $customer->orderItems = [new Item($this->db)];
            $this->fail('setter call above MUST throw Exception');
        } catch (Exception $e) {
            /** catch exception "Setting read-only property" */
            $this->assertInstanceOf(InvalidCallException::class, $e);
        }

        /** related attribute $customer->orderItems didn't change cause it's read-only */
        $this->assertSame([], $customer->orderItems);
        $this->assertFalse($customer->canGetProperty('non_existing_property'));
        $this->assertFalse($customer->canSetProperty('non_existing_property'));

        $this->expectException(UnknownPropertyException::class);
        $this->expectExceptionMessage('Setting unknown property: ' . Customer::class . '::non_existing_property');
        $customer->non_existing_property = null;
    }

    public function testHasAttribute(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new Customer($this->db);

        $this->assertTrue($customer->hasAttribute('id'));
        $this->assertTrue($customer->hasAttribute('email'));
        $this->assertFalse($customer->hasAttribute(0));
        $this->assertFalse($customer->hasAttribute(null));
        $this->assertFalse($customer->hasAttribute(42));

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(1);
        $this->assertTrue($customer->hasAttribute('id'));
        $this->assertTrue($customer->hasAttribute('email'));
        $this->assertFalse($customer->hasAttribute(0));
        $this->assertFalse($customer->hasAttribute(null));
        $this->assertFalse($customer->hasAttribute(42));
    }

    public function testRefresh(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customer = new Customer($this->db);

        $this->assertFalse($customer->refresh());

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(1);
        $customer->name = 'to be refreshed';

        $this->assertTrue($customer->refresh());
        $this->assertEquals('user1', $customer->name);
    }

    public function testEquals(): void
    {
        $this->checkFixture($this->db, 'customer');

        $customerA = new Customer($this->db);
        $customerB = new Customer($this->db);
        $this->assertFalse($customerA->equals($customerB));

        $customerA = new Customer($this->db);
        $customerB = new Item($this->db);
        $this->assertFalse($customerA->equals($customerB));
    }

    public static function providerForUnlinkDelete()
    {
        return [
            'with delete' => [true, 0],
            'without delete' => [false, 1],
        ];
    }

    /**
     * @dataProvider providerForUnlinkDelete
     *
     * @see https://github.com/yiisoft/yii2/issues/17174
     */
    public function testUnlinkWithViaOnCondition($delete, $count)
    {
        $this->checkFixture($this->db, 'order', true);
        $this->checkFixture($this->db, 'order_item_with_null_fk', true);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        $order = $orderQuery->findOne(2);

        $this->assertCount(1, $order->itemsFor8);
        $order->unlink('itemsFor8', $order->itemsFor8[0], $delete);

        $order = $orderQuery->findOne(2);
        $this->assertCount(0, $order->itemsFor8);
        $this->assertCount(2, $order->orderItemsWithNullFK);

        $orderItemQuery = new ActiveQuery(OrderItemWithNullFK::class, $this->db);
        $this->assertCount(1, $orderItemQuery->findAll([
            'order_id' => 2,
            'item_id' => 5,
        ]));
        $this->assertCount($count, $orderItemQuery->findAll([
            'order_id' => null,
            'item_id' => null,
        ]));
    }

    public function testVirtualRelation()
    {
        $this->checkFixture($this->db, 'order', true);

        $orderQuery = new ActiveQuery(Order::class, $this->db);
        /** @var Order $order */
        $order = $orderQuery->findOne(2);

        $order->setVirtualCustomerId($order->customer_id);
        $this->assertNotNull($order->getVirtualCustomer());
    }

    /**
     * Test joinWith eager loads via relation
     *
     * @see https://github.com/yiisoft/yii2/issues/19507
     */
    public function testJoinWithEager()
    {
        $this->checkFixture($this->db, 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $eagerCustomers = $customerQuery->joinWith(['items2'])->all();
        $eagerItemsCount = 0;
        foreach ($eagerCustomers as $customer) {
            $eagerItemsCount += is_countable($customer->items2) ? count($customer->items2) : 0;
        }

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $lazyCustomers = $customerQuery->all();
        $lazyItemsCount = 0;
        foreach ($lazyCustomers as $customer) {
            $lazyItemsCount += is_countable($customer->items2) ? count($customer->items2) : 0;
        }

        $this->assertEquals($eagerItemsCount, $lazyItemsCount);
    }

    public function testToArray(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class, $this->db);
        $customer = $customerQuery->findOne(1);

        $this->assertSame(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 1,
                'profile_id' => 1,
            ],
            $customer->toArray(),
        );
    }

    public function testToArrayWithClosure(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        $customerQuery = new ActiveQuery(CustomerClosureField::class, $this->db);
        $customer = $customerQuery->findOne(1);

        $this->assertSame(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 'active',
                'profile_id' => 1,
            ],
            $customer->toArray(),
        );
    }

    public function testToArrayForArrayable(): void
    {
        $this->checkFixture($this->db, 'customer', true);

        $customerQuery = new ActiveQuery(CustomerForArrayable::class, $this->db);

        /** @var CustomerForArrayable $customer */
        $customer = $customerQuery->findOne(1);
        /** @var CustomerForArrayable $customer2 */
        $customer2 = $customerQuery->findOne(2);
        /** @var CustomerForArrayable $customer3 */
        $customer3 = $customerQuery->findOne(3);

        $customer->setItem($customer2);
        $customer->setItems($customer3);

        $this->assertSame(
            [
                'id' => 1,
                'email' => 'user1@example.com',
                'name' => 'user1',
                'address' => 'address1',
                'status' => 'active',
                'item' => [
                    'id' => 2,
                    'email' => 'user2@example.com',
                    'name' => 'user2',
                    'status' => 'active',
                ],
                'items' => [
                    [
                        'id' => 3,
                        'email' => 'user3@example.com',
                        'name' => 'user3',
                        'status' => 'inactive',
                    ],
                ],
            ],
            $customer->toArray([
                'id',
                'name',
                'email',
                'address',
                'status',
                'item.id',
                'item.name',
                'item.email',
                'items.0.id',
                'items.0.name',
                'items.0.email',
            ]),
        );
    }

    public function testSaveWithoutChanges()
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $customer = $customerQuery->findOne(1);

        $this->assertTrue($customer->save());
    }

    public function testGetPrimaryKey()
    {
        $this->checkFixture($this->db, 'customer');

        $customerQuery = new ActiveQuery(Customer::class, $this->db);

        $customer = $customerQuery->findOne(1);

        $this->assertSame(1, $customer->getPrimaryKey());
        $this->assertSame(['id' => 1], $customer->getPrimaryKey(true));
    }
}
