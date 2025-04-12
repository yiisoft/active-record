<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use DateTimeImmutable;
use DivisionByZeroError;
use ReflectionException;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Alpha;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Animal;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Cat;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\CustomerWithAlias;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\CustomerWithProperties;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Dog;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\NoExist;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\NullValues;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\OrderItem;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\OrderItemWithNullFK;
use Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Type;
use Yiisoft\ActiveRecord\Tests\Support\Assert;
use Yiisoft\Db\Exception\Exception;
use Yiisoft\Db\Exception\InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Exception\UnknownPropertyException;
use Yiisoft\Db\Query\Query;

abstract class MagicActiveRecordTest extends TestCase
{
    public function testStoreNull(): void
    {
        $this->checkFixture($this->db(), 'null_values', true);

        $record = new NullValues();

        $this->assertNull($record->get('var1'));
        $this->assertNull($record->get('var2'));
        $this->assertNull($record->get('var3'));
        $this->assertNull($record->get('stringcol'));

        $record->set('var1', 123);
        $record->set('var2', 456);
        $record->set('var3', 789);
        $record->set('stringcol', 'hello!');
        $record->save();

        $this->assertTrue($record->refresh());
        $this->assertEquals(123, $record->get('var1'));
        $this->assertEquals(456, $record->get('var2'));
        $this->assertEquals(789, $record->get('var3'));
        $this->assertEquals('hello!', $record->get('stringcol'));

        $record->set('var1', null);
        $record->set('var2', null);
        $record->set('var3', null);
        $record->set('stringcol', null);
        $record->save();

        $this->assertTrue($record->refresh());
        $this->assertNull($record->get('var1'));
        $this->assertNull($record->get('var2'));
        $this->assertNull($record->get('var3'));
        $this->assertNull($record->get('>stringcol'));

        $record->set('var1', 0);
        $record->set('var2', 0);
        $record->set('var3', 0);
        $record->set('stringcol', '');
        $record->save();

        $this->assertTrue($record->refresh());
        $this->assertEquals(0, $record->get('var1'));
        $this->assertEquals(0, $record->get('var2'));
        $this->assertEquals(0, $record->get('var3'));
        $this->assertEquals('', $record->get('stringcol'));
    }

    public function testStoreEmpty(): void
    {
        $this->checkFixture($this->db(), 'null_values');

        $record = new NullValues();

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
        $this->checkFixture($this->db(), 'customer');

        $customer = new Customer();
        $orderItem = new OrderItem();

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
        $this->checkFixture($this->db(), 'order_item');

        $orderItem = new OrderItem();

        $orderItem->order_id = 1;
        $orderItem->item_id = 3;
        $this->assertEquals(1, $orderItem->order->id);
        $this->assertEquals(3, $orderItem->item->id);

        /** test `__set()`. */
        $orderItem->order_id = 2;
        $orderItem->item_id = 1;
        $this->assertEquals(2, $orderItem->order->id);
        $this->assertEquals(1, $orderItem->item->id);

        /** test `set()`. */
        $orderItem->set('order_id', 2);
        $orderItem->set('item_id', 2);
        $this->assertEquals(2, $orderItem->order->id);
        $this->assertEquals(2, $orderItem->item->id);
    }

    public function testDefaultValues(): void
    {
        $this->checkFixture($this->db(), 'type');

        $arClass = new Type();

        $arClass->loadDefaultValues();

        $this->assertEquals(1, $arClass->int_col2);
        $this->assertEquals('something', $arClass->char_col2);
        $this->assertEquals(1.23, $arClass->float_col2);
        $this->assertEquals(33.22, $arClass->numeric_col);
        $this->assertTrue($arClass->bool_col2);
        $this->assertEquals('2002-01-01 00:00:00', $arClass->time);

        if ($this->db()->getDriverName() !== 'mysql') {
            $this->assertSame(['a' => 1], $arClass->json_col);
        }

        $arClass = new Type();
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues();
        $this->assertEquals('not something', $arClass->char_col2);

        $arClass = new Type();
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues(false);
        $this->assertEquals('something', $arClass->char_col2);
    }

    public function testCastValues(): void
    {
        $this->checkFixture($this->db(), 'type');

        $arClass = new Type();

        $arClass->deleteAll();

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
        $arClass->json_col = ['a' => 'b', 'c' => null, 'd' => [1, 2, 3]];

        $arClass->save();

        /** @var $model Type */
        $aqClass = new ActiveQuery(Type::class);
        $query = $aqClass->one();

        $this->assertSame(123, $query->int_col);
        $this->assertSame(456, $query->int_col2);
        $this->assertSame(42, $query->smallint_col);
        $this->assertSame('1337', trim($query->char_col));
        $this->assertSame('test', $query->char_col2);
        $this->assertSame('test123', $query->char_col3);
        $this->assertSame(3.742, $query->float_col);
        $this->assertSame(42.1337, $query->float_col2);
        $this->assertEquals(true, $query->bool_col);
        $this->assertEquals(false, $query->bool_col2);
        $this->assertSame(['a' => 'b', 'c' => null, 'd' => [1, 2, 3]], $query->json_col);
    }

    public function testPopulateRecordCallWhenQueryingOnParentClass(): void
    {
        $this->checkFixture($this->db(), 'cat');

        $cat = new Cat();
        $cat->save();

        $dog = new Dog();
        $dog->save();

        $animal = new ActiveQuery(Animal::class);

        $animals = $animal->where(['type' => Dog::class])->one();
        $this->assertEquals('bark', $animals->getDoes());

        $animals = $animal->where(['type' => Cat::class])->one();
        $this->assertEquals('meow', $animals->getDoes());
    }

    public function testSaveEmpty(): void
    {
        $this->checkFixture($this->db(), 'null_values', true);

        $record = new NullValues();

        $this->assertTrue($record->save());
        $this->assertEquals(1, $record->id);
    }

    /**
     * Verify that {{}} are not going to be replaced in parameters.
     */
    public function testNoTablenameReplacement(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new Customer();

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
        $this->checkFixture($this->db(), 'customer');

        $activeQuery = new ActiveQuery($modelClassName);

        if ($alias !== null) {
            $activeQuery->alias('csr');
        }

        /** @var Query $query */
        $query = Assert::invokeMethod($activeQuery, 'findByCondition', [$validFilter]);


        $this->db()->getQueryBuilder()->build($query);

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
        $this->checkFixture($this->db(), 'customer');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/^Key "(.+)?" is not a column name and can not be used as a filter$/'
        );

        $query = new ActiveQuery($modelClassName);

        /** @var Query $query */
        $query = Assert::invokeMethod($query, 'findByCondition', $filterWithInjection);

        $this->db()->getQueryBuilder()->build($query);
    }

    public function testRefreshQuerySetAliasFindRecord(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new CustomerWithAlias();

        $customer->id = 1;
        $customer->refresh();

        $this->assertEquals(1, $customer->id);
    }

    public function testResetNotSavedRelation(): void
    {
        $this->checkFixture($this->db(), 'order');

        $order = new Order();

        $order->customer_id = 1;
        $order->created_at = 1_325_502_201;
        $order->total = 0;

        $orderItem = new OrderItem();

        $order->orderItems;

        $order->populateRelation('orderItems', [$orderItem]);

        $order->save();

        $this->assertCount(1, $order->orderItems);
    }

    public function testIssetException(): void
    {
        $this->checkFixture($this->db(), 'cat');

        $cat = new Cat();

        $this->expectException(Exception::class);
        isset($cat->exception);
    }

    public function testIssetThrowable(): void
    {
        $this->checkFixture($this->db(), 'cat');

        $cat = new Cat();

        $this->expectException(DivisionByZeroError::class);
        isset($cat->throwable);
    }

    public function testIssetNonExisting(): void
    {
        $this->checkFixture($this->db(), 'cat');

        $cat = new Cat();

        $this->assertFalse(isset($cat->non_existing));
        $this->assertFalse(isset($cat->non_existing_property));
    }

    public function testAssignProperties(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $properties = [
            'email' => 'samdark@mail.ru',
            'name' => 'samdark',
            'address' => 'rusia',
            'status' => 1,
        ];

        if ($this->db()->getDriverName() === 'pgsql') {
            $properties['bool_status'] = true;
        }

        $properties['profile_id'] = null;

        $customer = new Customer();

        $customer->populateProperties($properties);

        $this->assertTrue($customer->save());
    }

    public function testSetNoExistProperty(): void
    {
        $this->checkFixture($this->db(), 'cat');

        $cat = new Cat();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Cat has no property named "noExist"'
        );

        $cat->set('noExist', 1);
    }

    public function testAssignOldValue(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new Customer();

        $this->assertEmpty($customer->oldValue('name'));

        $customer->assignOldValue('name', 'samdark');

        $this->assertEquals('samdark', $customer->oldValue('name'));
    }

    public function testAssignOldValueException(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new Customer();

        $this->assertEmpty($customer->oldValue('name'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Customer has no property named "noExist"'
        );
        $customer->assignOldValue('noExist', 'samdark');
    }

    public function testIsPropertyChangedNotChanged(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new Customer();

        $this->assertEmpty($customer->get('name'));
        $this->assertEmpty($customer->oldValue('name'));
        $this->assertFalse($customer->isPropertyChanged('name'));
        $this->assertFalse($customer->isPropertyChangedNonStrict('name'));
    }

    public function testTableSchemaException(): void
    {
        $noExist = new NoExist();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The table does not exist: NoExist');
        $noExist->getTableSchema();
    }

    public function testInsert(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new Customer();

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
    public function testBooleanProperty(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        $customer = new Customer();

        $customer->name = 'boolean customer';
        $customer->email = 'mail@example.com';
        $customer->bool_status = true;

        $customer->save();
        $customer->refresh();
        $this->assertTrue($customer->bool_status);

        $customer->bool_status = false;
        $customer->save();

        $customer->refresh();
        $this->assertFalse($customer->bool_status);

        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->where(['bool_status' => true])->all();
        $this->assertCount(2, $customers);

        $customerQuery = new ActiveQuery(Customer::class);
        $customers = $customerQuery->where(['bool_status' => false])->all();
        $this->assertCount(2, $customers);
    }

    public function testPropertyAccess(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $arClass = new Customer();

        $this->assertTrue($arClass->canSetProperty('name'));
        $this->assertTrue($arClass->canGetProperty('name'));
        $this->assertFalse($arClass->canSetProperty('unExistingColumn'));
        $this->assertFalse(isset($arClass->name));

        $arClass->name = 'foo';
        $this->assertTrue(isset($arClass->name));

        unset($arClass->name);
        $this->assertNull($arClass->name);

        /** {@see https://github.com/yiisoft/yii2-gii/issues/190} */
        $baseModel = new Customer();
        $this->assertFalse($baseModel->hasProperty('unExistingColumn'));

        $customer = new Customer();
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

        $this->expectException(InvalidCallException::class);
        $this->expectExceptionMessage('Setting read-only property: ' . Customer::class . '::orderItems');
        $customer->orderItems = [new Item()];

        /** related property $customer->orderItems didn't change cause it's read-only */
        $this->assertSame([], $customer->orderItems);
        $this->assertFalse($customer->canGetProperty('non_existing_property'));
        $this->assertFalse($customer->canSetProperty('non_existing_property'));

        $this->expectException(UnknownPropertyException::class);
        $this->expectExceptionMessage('Setting unknown property: ' . Customer::class . '::non_existing_property');
        $customer->non_existing_property = null;
    }

    public function testHasProperty(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new Customer();

        $this->assertTrue($customer->hasProperty('id'));
        $this->assertTrue($customer->hasProperty('email'));
        $this->assertFalse($customer->hasProperty('notExist'));

        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findOne(1);
        $this->assertTrue($customer->hasProperty('id'));
        $this->assertTrue($customer->hasProperty('email'));
        $this->assertFalse($customer->hasProperty('notExist'));
    }

    public function testRefresh(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new Customer();

        $this->assertFalse($customer->refresh());

        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findOne(1);
        $customer->name = 'to be refreshed';

        $this->assertTrue($customer->refresh());
        $this->assertEquals('user1', $customer->name);
    }

    public function testEquals(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerA = new Customer();
        $customerB = new Customer();
        $this->assertFalse($customerA->equals($customerB));

        $customerA = new Customer();
        $customerB = new Item();
        $this->assertFalse($customerA->equals($customerB));
    }

    public static function providerForUnlinkDelete(): array
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
    public function testUnlinkWithViaOnCondition($delete, $count): void
    {
        $this->checkFixture($this->db(), 'order', true);
        $this->checkFixture($this->db(), 'order_item_with_null_fk', true);

        $orderQuery = new ActiveQuery(Order::class);
        $order = $orderQuery->findOne(2);

        $this->assertCount(1, $order->itemsFor8);
        $order->unlink('itemsFor8', $order->itemsFor8[0], $delete);

        $order = $orderQuery->findOne(2);
        $this->assertCount(0, $order->itemsFor8);
        $this->assertCount(2, $order->orderItemsWithNullFK);

        $orderItemQuery = new ActiveQuery(OrderItemWithNullFK::class);
        $this->assertCount(1, $orderItemQuery->findAll([
            'order_id' => 2,
            'item_id' => 5,
        ]));
        $this->assertCount($count, $orderItemQuery->findAll([
            'order_id' => null,
            'item_id' => null,
        ]));
    }

    public function testVirtualRelation(): void
    {
        $this->checkFixture($this->db(), 'order', true);

        $orderQuery = new ActiveQuery(Order::class);
        /** @var Order $order */
        $order = $orderQuery->findOne(2);

        $order->setVirtualCustomerId($order->customer_id);
        $this->assertNotNull($order->getVirtualCustomerQuery());
    }

    /**
     * Test joinWith eager loads via relation
     *
     * @see https://github.com/yiisoft/yii2/issues/19507
     */
    public function testJoinWithEager(): void
    {
        $this->checkFixture($this->db(), 'customer', true);

        $customerQuery = new ActiveQuery(Customer::class);
        $eagerCustomers = $customerQuery->joinWith(['items2'])->all();
        $eagerItemsCount = 0;
        foreach ($eagerCustomers as $customer) {
            $eagerItemsCount += is_countable($customer->items2) ? count($customer->items2) : 0;
        }

        $customerQuery = new ActiveQuery(Customer::class);
        $lazyCustomers = $customerQuery->all();
        $lazyItemsCount = 0;
        foreach ($lazyCustomers as $customer) {
            $lazyItemsCount += is_countable($customer->items2) ? count($customer->items2) : 0;
        }

        $this->assertEquals($eagerItemsCount, $lazyItemsCount);
    }

    public function testSaveWithoutChanges(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        $customer = $customerQuery->findOne(1);

        $this->assertTrue($customer->save());
    }

    public function testGetPrimaryKey(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        $customer = $customerQuery->findOne(1);

        $this->assertSame(1, $customer->getPrimaryKey());
        $this->assertSame(['id' => 1], $customer->getPrimaryKey(true));
    }

    public function testGetOldPrimaryKey(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);

        $customer = $customerQuery->findOne(1);
        $customer->id = 2;

        $this->assertSame(1, $customer->getOldPrimaryKey());
        $this->assertSame(['id' => 1], $customer->getOldPrimaryKey(true));
    }

    public function testGetDirtyValuesOnNewRecord(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new Customer();

        $this->assertSame([], $customer->newValues());

        $customer->set('name', 'Adam');
        $customer->set('email', 'adam@example.com');
        $customer->set('address', null);

        $this->assertEquals(
            ['name' => 'Adam', 'email' => 'adam@example.com', 'address' => null],
            $customer->newValues()
        );
        $this->assertEquals(
            ['email' => 'adam@example.com', 'address' => null],
            $customer->newValues(['id', 'email', 'address', 'status', 'unknown']),
        );

        $this->assertTrue($customer->save());
        $this->assertSame([], $customer->newValues());

        $customer->set('address', '');

        $this->assertSame(['address' => ''], $customer->newValues());
    }

    public function testGetDirtyValuesAfterFind(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customerQuery = new ActiveQuery(Customer::class);
        $customer = $customerQuery->findOne(1);

        $this->assertSame([], $customer->newValues());

        $customer->set('name', 'Adam');
        $customer->set('email', 'adam@example.com');
        $customer->set('address', null);

        $this->assertEquals(
            ['name' => 'Adam', 'email' => 'adam@example.com', 'address' => null],
            $customer->newValues(),
        );
        $this->assertEquals(
            ['email' => 'adam@example.com', 'address' => null],
            $customer->newValues(['id', 'email', 'address', 'status', 'unknown']),
        );
    }

    public function testGetDirtyValuesWithProperties(): void
    {
        $this->checkFixture($this->db(), 'customer');

        $customer = new CustomerWithProperties();
        $this->assertSame([
            'name' => null,
            'address' => null,
        ], $customer->newValues());

        $customerQuery = new ActiveQuery(CustomerWithProperties::class);
        $customer = $customerQuery->findOne(1);

        $this->assertSame([], $customer->newValues());

        $customer->setEmail('adam@example.com');
        $customer->setName('Adam');
        $customer->setAddress(null);
        $customer->setStatus(null);

        $this->assertEquals(
            ['email' => 'adam@example.com', 'name' => 'Adam', 'address' => null, 'status' => null],
            $customer->newValues(),
        );
        $this->assertEquals(
            ['email' => 'adam@example.com', 'address' => null],
            $customer->newValues(['id', 'email', 'address', 'unknown']),
        );
    }

    public function testRelationNames(): void
    {
        $this->checkFixture($this->db(), 'animal');

        $animal = new Animal();

        $this->assertEmpty($animal->relationNames());

        $alpha = new Alpha();

        $this->assertSame(['betas'], $alpha->relationNames());

        $customer = new Customer();

        $this->assertSame([
            'profile',
            'ordersPlain',
            'orders',
            'ordersNoOrder',
            'expensiveOrders',
            'ordersWithItems',
            'expensiveOrdersWithNullFK',
            'ordersWithNullFK',
            'orders2',
            'orderItems',
            'orderItems2',
            'items2',
        ], $customer->relationNames());
    }

    public function testGetSetMethodsPriority(): void
    {
        $this->checkFixture($this->db(), 'order');

        $datetime = DateTimeImmutable::createFromFormat('U', '1325502201');

        $order = new Order();
        $order->created_at = $datetime;

        $this->assertSame(1_325_502_201, $order->get('created_at'));
        $this->assertEquals($datetime, $order->created_at);
    }

    public function testIsChanged(): void
    {
        $this->checkFixture($this->db(), 'item');

        $itemQuery = new ActiveQuery(Item::class);
        $item = $itemQuery->findOne(1);

        $this->assertFalse($item->isChanged());

        $item->set('name', 'New name');

        $this->assertTrue($item->isChanged());

        $newItem = new Item();

        $this->assertFalse($newItem->isChanged());

        $newItem->set('name', 'New name');

        $this->assertTrue($newItem->isChanged());
    }
}
