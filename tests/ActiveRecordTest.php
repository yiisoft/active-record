<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests;

use ArgumentCountError;
use DivisionByZeroError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Yiisoft\ActiveRecord\ActiveQuery;
use Yiisoft\ActiveRecord\ArArrayHelper;
use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\ActiveRecord\Event\AfterDelete;
use Yiisoft\ActiveRecord\Event\EventDispatcherProvider;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Animal;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CategoryAfterDelete;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\StopPropagation;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Cat;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerWithAlias;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerWithFactory;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\CustomerWithCustomConnection;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\DefaultValueAr;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\DefaultValueOnInsertAr;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Dog;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Item;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\NoExist;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\NullValues;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Order;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderItem;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderItemWithNullFK;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\OrderWithFactory;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Promotion;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Profile;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\SetValueOnUpdateAr;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Type;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\User;
use Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\UserProfile;
use Yiisoft\ActiveRecord\Tests\Support\DbHelper;
use Yiisoft\ActiveRecord\Tests\Support\ModelFactory;
use Yiisoft\Db\Exception\Exception;
use InvalidArgumentException;
use Yiisoft\Db\Exception\InvalidCallException;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\ActiveRecord\UnknownPropertyException;
use Yiisoft\Db\Expression\Expression;
use Yiisoft\Factory\Factory;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;

abstract class ActiveRecordTest extends TestCase
{
    abstract protected function createFactory(): Factory;

    public function testStoreNull(): void
    {
        $this->reloadFixtureAfterTest();

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
        $this->reloadFixtureAfterTest();

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
        $orderItem = new OrderItem();

        $orderItem->setOrderId(1);
        $orderItem->setItemId(3);
        $this->assertEquals(1, $orderItem->getOrder()->getId());
        $this->assertEquals(3, $orderItem->getItem()->getId());

        $orderItem->setOrderId(2);
        $orderItem->setItemId(1);
        $this->assertEquals(2, $orderItem->getOrder()->getId());
        $this->assertEquals(1, $orderItem->getItem()->getId());

        /** test `set()`. */
        $orderItem->set('order_id', 2);
        $orderItem->set('item_id', 2);
        $this->assertEquals(2, $orderItem->getOrder()->getId());
        $this->assertEquals(2, $orderItem->getItem()->getId());
    }

    public function testDefaultValues(): void
    {
        $arClass = new Type();

        $arClass->loadDefaultValues();

        $this->assertSame(1, $arClass->int_col2);
        $this->assertSame('something', $arClass->char_col2);
        $this->assertSame(1.23, $arClass->float_col2);
        $this->assertSame(33.22, $arClass->numeric_col);
        $this->assertTrue($arClass->bool_col2);
        $this->assertSame('2002-01-01 00:00:00', $arClass->time);

        if ($this->db()->getDriverName() !== 'mysql') {
            $this->assertSame(['a' => 1], $arClass->json_col);
        }

        $arClass = new Type();
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues();
        $this->assertSame('not something', $arClass->char_col2);

        $arClass = new Type();
        $arClass->char_col2 = 'not something';

        $arClass->loadDefaultValues(false);
        $this->assertSame('something', $arClass->char_col2);
    }

    public function testCastValues(): void
    {
        $this->reloadFixtureAfterTest();

        $arClass = new Type();

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
        $arClass->time = new Expression('CURRENT_TIMESTAMP');
        $arClass->json_col = ['a' => 'b', 'c' => null, 'd' => [1, 2, 3]];

        $arClass->save();

        /** @var $model Type */
        $aqClass = Type::query();
        $query = $aqClass->one();

        $this->assertSame(123, $query->int_col);
        $this->assertSame(456, $query->int_col2);
        $this->assertSame(42, $query->smallint_col);
        $this->assertSame('1337', trim($query->char_col));
        $this->assertSame('test', $query->char_col2);
        $this->assertSame('test123', $query->char_col3);
        $this->assertSame(3.742, $query->float_col);
        $this->assertSame(42.1337, $query->float_col2);
        $this->assertTrue($query->bool_col);
        $this->assertFalse($query->bool_col2);
        $this->assertSame(['a' => 'b', 'c' => null, 'd' => [1, 2, 3]], $query->json_col);
    }

    public function testPopulateRecordCallWhenQueryingOnParentClass(): void
    {
        $this->reloadFixtureAfterTest();

        $cat = new Cat();
        $cat->save();

        $dog = new Dog();
        $dog->save();

        $animal = Animal::query()->resultCallback(ModelFactory::create(...));

        $animals = $animal->where(['type' => Dog::class])->one();
        $this->assertEquals('bark', $animals->getDoes());

        $animals = $animal->setWhere(['type' => Cat::class])->one();
        $this->assertEquals('meow', $animals->getDoes());
    }

    public function testSave(): void
    {
        $this->reloadFixtureAfterTest();

        // insert
        $customer = new Customer();

        $customer->setEmail('user4@example.com');
        $customer->setName('user4');
        $customer->setAddress('address4');
        $customer->setStatus(1);

        $this->assertNull($customer->getId());
        $this->assertTrue($customer->isNewRecord());

        $this->assertTrue($customer->save());
        $this->assertFalse($customer->isNewRecord());
        $this->assertSame(4, $customer->getId());

        $customer->refresh();
        $this->assertSame(4, $customer->getId());
        $this->assertSame('user4@example.com', $customer->getEmail());
        $this->assertSame('user4', $customer->getName());
        $this->assertSame('address4', $customer->getAddress());
        $this->assertSame(1, $customer->getStatus());

        // insert with property names
        $customer = new Customer();

        $customer->setEmail('user5@example.com');
        $customer->setName('user5');
        $customer->setAddress('address5');
        $customer->setStatus(1);

        $this->assertNull($customer->getId());
        $this->assertTrue($customer->isNewRecord());

        $this->assertTrue($customer->save(['email', 'name', 'address']));
        $this->assertFalse($customer->isNewRecord());
        $this->assertSame(5, $customer->getId());

        $customer->refresh();
        $this->assertSame(5, $customer->getId());
        $this->assertSame('user5@example.com', $customer->getEmail());
        $this->assertSame('user5', $customer->getName());
        $this->assertSame('address5', $customer->getAddress());
        $this->assertSame(0, $customer->getStatus());

        // insert with property values
        $customer = new Customer();
        $customer->setAddress('address6');
        $customer->setStatus(1);

        $this->assertTrue($customer->save([
            'email' => 'user6@example.com',
            'name' => 'user6',
            'address',
        ]));
        $this->assertFalse($customer->isNewRecord());
        $this->assertSame(6, $customer->getId());

        $customer->refresh();
        $this->assertSame(6, $customer->getId());
        $this->assertSame('user6@example.com', $customer->getEmail());
        $this->assertSame('user6', $customer->getName());
        $this->assertSame('address6', $customer->getAddress());
        $this->assertSame(0, $customer->getStatus());

        // update
        $customer->setEmail('customer6@example.com');
        $customer->setName('customer6');

        $this->assertTrue($customer->save());

        $this->assertFalse($customer->isNewRecord());
        $this->assertSame(6, $customer->getId());

        $customer->refresh();
        $this->assertSame(6, $customer->getId());
        $this->assertSame('customer6@example.com', $customer->getEmail());
        $this->assertSame('customer6', $customer->getName());
        $this->assertSame('address6', $customer->getAddress());
        $this->assertSame(0, $customer->getStatus());

        // update with property names
        $customer->setEmail('name6@example.com');
        $customer->setName('name6');
        $customer->setStatus(1);

        $this->assertTrue($customer->save(['email', 'name']));

        $customer->refresh();
        $this->assertSame(6, $customer->getId());
        $this->assertSame('name6@example.com', $customer->getEmail());
        $this->assertSame('name6', $customer->getName());
        $this->assertSame('address6', $customer->getAddress());
        $this->assertSame(0, $customer->getStatus());

        // update with property values
        $customer->setAddress('street6');
        $customer->setStatus(1);
        $this->assertTrue($customer->save([
            'email' => 'client6@example.com',
            'name' => 'client6',
            'address',
        ]));

        $customer->refresh();
        $this->assertSame(6, $customer->getId());
        $this->assertSame('client6@example.com', $customer->getEmail());
        $this->assertSame('client6', $customer->getName());
        $this->assertSame('street6', $customer->getAddress());
        $this->assertSame(0, $customer->getStatus());
    }

    public function testSaveEmpty(): void
    {
        $this->reloadFixtureAfterTest();

        $record = new NullValues();

        $this->assertTrue($record->save());
        $this->assertEquals(1, $record->id);
    }

    /**
     * Verify that {{}} are not going to be replaced in parameters.
     */
    public function testNoTablenameReplacement(): void
    {
        $this->reloadFixtureAfterTest();

        $customer = new Customer();

        $customer->setName('Some {{weird}} name');
        $customer->setEmail('test@example.com');
        $customer->setAddress('Some {{%weird}} address');
        $customer->insert();
        $customer->refresh();

        $this->assertEquals('Some {{weird}} name', $customer->getName());
        $this->assertEquals('Some {{%weird}} address', $customer->getAddress());

        $customer->setName('Some {{updated}} name');
        $customer->setAddress('Some {{%updated}} address');
        $customer->update();

        $this->assertEquals('Some {{updated}} name', $customer->getName());
        $this->assertEquals('Some {{%updated}} address', $customer->getAddress());
    }

    public function testRefreshQuerySetAliasFindRecord(): void
    {
        $customer = new CustomerWithAlias();

        $customer->id = 1;
        $customer->refresh();

        $this->assertEquals(1, $customer->id);
    }

    public function testResetNotSavedRelation(): void
    {
        $this->reloadFixtureAfterTest();

        $order = new Order();

        $order->setCustomerId(1);
        $order->setCreatedAt(1_325_502_201);
        $order->setTotal(0);

        $orderItem = new OrderItem();

        $order->getOrderItems();

        $order->populateRelation('orderItems', [$orderItem]);

        $order->save();

        $this->assertCount(1, $order->getOrderItems());
    }

    public function testIssetException(): void
    {
        self::markTestSkipped('There are no magic properties in the Cat class');

        $cat = new Cat();

        $this->expectException(Exception::class);
        isset($cat->exception);
    }

    public function testIssetThrowable(): void
    {
        self::markTestSkipped('There are no magic properties in the Cat class');

        $cat = new Cat();

        $this->expectException(DivisionByZeroError::class);
        isset($cat->throwable);
    }

    public function testIssetNonExisting(): void
    {
        self::markTestSkipped('There are no magic properties in the Cat class');

        $cat = new Cat();

        $this->assertFalse(isset($cat->non_existing));
        $this->assertFalse(isset($cat->non_existing_property));
    }

    public function testSetProperties(): void
    {
        $this->reloadFixtureAfterTest();

        $properties = [
            'email' => 'samdark@mail.ru',
            'name' => 'samdark',
            'address' => 'rusia',
            'status' => 1,
            'bool_status' => true,
        ];

        $properties['profile_id'] = null;

        $customer = new Customer();

        $customer->populateProperties($properties);

        $this->assertTrue($customer->save());
    }

    public function testSetPropertyNoExist(): void
    {
        self::markTestSkipped('There are no magic properties in the Cat class');

        $cat = new Cat();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Yiisoft\ActiveRecord\Tests\Stubs\MagicActiveRecord\Cat has no property named "noExist"'
        );

        $cat->set('noExist', 1);
    }

    public function testAssignOldValue(): void
    {
        $customer = new Customer();

        $this->assertEmpty($customer->oldValue('name'));

        $customer->assignOldValue('name', 'samdark');

        $this->assertEquals('samdark', $customer->oldValue('name'));
    }

    public function testaAssignOldValueException(): void
    {
        $customer = new Customer();

        $this->assertEmpty($customer->oldValue('name'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Customer has no property named "noExist"'
        );
        $customer->assignOldValue('noExist', 'samdark');
    }

    public function testIsPropertyChangedNotChanged(): void
    {
        $customer = new Customer();

        $this->assertEmpty($customer->get('email'));
        $this->assertEmpty($customer->oldValue('email'));
        $this->assertFalse($customer->isPropertyChanged('email'));
        $this->assertFalse($customer->isPropertyChangedNonStrict('email'));
    }

    public function testTableSchemaException(): void
    {
        $noExist = new NoExist();

        $this->expectException(InvalidConfigException::class);
        $this->expectExceptionMessage('The table does not exist: NoExist');
        $noExist->tableSchema();
    }

    public function testInsert(): void
    {
        $this->reloadFixtureAfterTest();

        $customer = new Customer();

        $customer->setEmail('user4@example.com');
        $customer->setName('user4');
        $customer->setAddress('address4');
        $customer->setStatus(1);

        $this->assertTrue($customer->isNewRecord());
        $this->assertNull($customer->getId());

        $this->assertTrue($customer->insert());
        $this->assertFalse($customer->isNewRecord());
        $this->assertSame(4, $customer->getId());

        $customer->refresh();
        $this->assertSame(4, $customer->getId());
        $this->assertSame('user4@example.com', $customer->getEmail());
        $this->assertSame('user4', $customer->getName());
        $this->assertSame('address4', $customer->getAddress());
        $this->assertSame(1, $customer->getStatus());

        // with property names
        $customer = new Customer();

        $customer->setEmail('user5@example.com');
        $customer->setName('user5');
        $customer->setAddress('address5');
        $customer->setStatus(1);

        $this->assertTrue($customer->isNewRecord());
        $this->assertNull($customer->getId());

        $this->assertTrue($customer->insert(['email', 'name', 'address']));
        $this->assertFalse($customer->isNewRecord());
        $this->assertSame(5, $customer->getId());

        $customer->refresh();
        $this->assertSame(5, $customer->getId());
        $this->assertSame('user5@example.com', $customer->getEmail());
        $this->assertSame('user5', $customer->getName());
        $this->assertSame('address5', $customer->getAddress());
        $this->assertSame(0, $customer->getStatus());

        // with property values
        $customer = new Customer();
        $customer->setAddress('address6');
        $customer->setStatus(1);

        $this->assertTrue($customer->insert([
            'email' => 'user6@example.com',
            'name' => 'user6',
            'address',
        ]));
        $this->assertFalse($customer->isNewRecord());
        $this->assertSame(6, $customer->getId());

        $customer->refresh();
        $this->assertSame(6, $customer->getId());
        $this->assertSame('user6@example.com', $customer->getEmail());
        $this->assertSame('user6', $customer->getName());
        $this->assertSame('address6', $customer->getAddress());
        $this->assertSame(0, $customer->getStatus());

        // insert not new record
        $this->expectException(InvalidCallException::class);
        $this->expectExceptionMessage('The record is not new and cannot be inserted.');

        $customer->insert();
    }

    /**
     * Some PDO implementations (e.g. cubrid) do not support boolean values.
     *
     * Make sure this does not affect AR layer.
     */
    public function testBooleanProperty(): void
    {
        $this->reloadFixtureAfterTest();

        $customer = new Customer();

        $customer->setName('boolean customer');
        $customer->setEmail('mail@example.com');
        $customer->setBoolStatus(true);

        $customer->save();
        $customer->refresh();
        $this->assertTrue($customer->getBoolStatus());

        $customer->setBoolStatus(false);
        $customer->save();

        $customer->refresh();
        $this->assertFalse($customer->getBoolStatus());

        $customerQuery = Customer::query();
        $customers = $customerQuery->where(['bool_status' => true])->all();
        $this->assertCount(2, $customers);

        $customerQuery = Customer::query();
        $customers = $customerQuery->where(['bool_status' => false])->all();
        $this->assertCount(2, $customers);
    }

    public function testPropertyAccess(): void
    {
        self::markTestSkipped('There are no magic properties in the Cat class');

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
        $customer = new Customer();

        $this->assertTrue($customer->hasProperty('id'));
        $this->assertTrue($customer->hasProperty('email'));
        $this->assertFalse($customer->hasProperty('notExist'));

        $customerQuery = Customer::query();
        $customer = $customerQuery->findByPk(1);
        $this->assertTrue($customer->hasProperty('id'));
        $this->assertTrue($customer->hasProperty('email'));
        $this->assertFalse($customer->hasProperty('notExist'));
    }

    public function testRefresh(): void
    {
        $customer = new Customer();

        $this->assertFalse($customer->refresh());

        $customerQuery = Customer::query();
        $customer = $customerQuery->findByPk(1);
        $customer->setName('to be refreshed');

        $this->assertTrue($customer->refresh());
        $this->assertEquals('user1', $customer->getName());
    }

    public function testEquals(): void
    {
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
        $this->reloadFixtureAfterTest();

        $orderQuery = Order::query();
        $order = $orderQuery->findByPk(2);

        $this->assertCount(1, $order->getItemsFor8());
        $order->unlink('itemsFor8', $order->getItemsFor8()[0], $delete);

        $order = $orderQuery->findByPk(2);
        $this->assertCount(0, $order->getItemsFor8());
        $this->assertCount(2, $order->getOrderItemsWithNullFK());

        $orderItemQuery = OrderItemWithNullFK::query();
        $this->assertCount(1, $orderItemQuery->where([
            'order_id' => 2,
            'item_id' => 5,
        ])->all());
        $this->assertCount($count, $orderItemQuery->setWhere([
            'order_id' => null,
            'item_id' => null,
        ])->all());
    }

    public function testVirtualRelation(): void
    {
        $orderQuery = Order::query();
        /** @var Order $order */
        $order = $orderQuery->findByPk(2);

        $order->setVirtualCustomerId($order->getCustomerId());
        $this->assertNotNull($order->getVirtualCustomerQuery());
    }

    /**
     * Test joinWith eager loads via relation
     *
     * @see https://github.com/yiisoft/yii2/issues/19507
     */
    public function testJoinWithEager(): void
    {
        $customerQuery = Customer::query();
        $eagerCustomers = $customerQuery->joinWith(['items2'])->all();
        $eagerItemsCount = 0;
        foreach ($eagerCustomers as $customer) {
            $eagerItemsCount += is_countable($customer->getItems2()) ? count($customer->getItems2()) : 0;
        }

        $customerQuery = Customer::query();
        $lazyCustomers = $customerQuery->all();
        $lazyItemsCount = 0;
        foreach ($lazyCustomers as $customer) {
            $lazyItemsCount += is_countable($customer->getItems2()) ? count($customer->getItems2()) : 0;
        }

        $this->assertEquals($eagerItemsCount, $lazyItemsCount);
    }

    public function testSaveWithoutChanges(): void
    {
        $customer = Customer::findByPk(1);

        $this->assertTrue($customer->save());
    }

    public function testPrimaryKeyValue(): void
    {
        $customer = Customer::findByPk(1);

        $this->assertSame(1, $customer->primaryKeyValue());
        $this->assertSame(['id' => 1], $customer->primaryKeyValues());

        $orderItemQuery = OrderItem::query();
        $orderItem = $orderItemQuery->findByPk([1, 2]);

        $this->assertSame(['order_id' => 1, 'item_id' => 2], $orderItem->primaryKeyValues());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(OrderItem::class . ' has multiple primary keys. Use primaryKeyValues() method instead.');

        $orderItem->primaryKeyValue();
    }

    public function testPrimaryKeyValueWithoutPrimaryKey(): void
    {
        $orderItem = new OrderItemWithNullFK();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(OrderItemWithNullFK::class . ' does not have a primary key.');

        $orderItem->primaryKeyValue();
    }

    public function testPrimaryKeyValuesWithoutPrimaryKey(): void
    {
        $orderItem = new OrderItemWithNullFK();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(OrderItemWithNullFK::class . ' does not have a primary key.');

        $orderItem->primaryKeyValues();
    }

    public function testPrimaryKeyOldValue(): void
    {
        $customer = Customer::findByPk(1);
        $customer->setId(2);

        $this->assertSame(1, $customer->primaryKeyOldValue());
        $this->assertSame(['id' => 1], $customer->primaryKeyOldValues());

        $orderItemQuery = OrderItem::query();
        $orderItem = $orderItemQuery->findByPk([1, 2]);
        $orderItem->setOrderId(3);
        $orderItem->setItemId(4);

        $this->assertSame(['order_id' => 1, 'item_id' => 2], $orderItem->primaryKeyOldValues());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(OrderItem::class . ' has multiple primary keys. Use primaryKeyOldValues() method instead.');

        $orderItem->primaryKeyOldValue();
    }

    public function testPrimaryKeyOldValueWithoutPrimaryKey(): void
    {
        $orderItem = new OrderItemWithNullFK();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(OrderItemWithNullFK::class . ' does not have a primary key.');

        $orderItem->primaryKeyOldValue();
    }

    public function testPrimaryKeyOldValuesWithoutPrimaryKey(): void
    {
        $orderItem = new OrderItemWithNullFK();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(OrderItemWithNullFK::class . ' does not have a primary key.');

        $orderItem->primaryKeyOldValues();
    }

    public function testGetDirtyValuesOnNewRecord(): void
    {
        $this->reloadFixtureAfterTest();

        $customer = new Customer();

        $this->assertSame(
            [
                'name' => null,
                'address' => null,
                'status' => 0,
                'bool_status' => false,
                'profile_id' => null,
            ],
            $customer->newValues()
        );

        $customer->set('name', 'Adam');
        $customer->set('email', 'adam@example.com');
        $customer->set('address', null);

        $this->assertSame([], $customer->newValues([]));

        $this->assertEquals(
            [
                'name' => 'Adam',
                'email' => 'adam@example.com',
                'address' => null,
                'status' => 0,
                'bool_status' => false,
                'profile_id' => null,
            ],
            $customer->newValues()
        );
        $this->assertSame(
            [
                'email' => 'adam@example.com',
                'address' => null,
                'status' => 0,
            ],
            $customer->newValues(['id', 'email', 'address', 'status', 'unknown']),
        );

        $this->assertTrue($customer->save());
        $this->assertSame([], $customer->newValues());

        $customer->set('address', '');

        $this->assertSame(['address' => ''], $customer->newValues());
    }

    public function testGetDirtyValuesAfterFind(): void
    {
        $customerQuery = Customer::query();
        $customer = $customerQuery->findByPk(1);

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

    public function testRelationWithInstance(): void
    {
        $customerQuery = Customer::query();
        $customer = $customerQuery->findByPk(2);

        $orders = $customer->getOrdersUsingInstance();

        $this->assertTrue($customer->isRelationPopulated('ordersUsingInstance'));
        $this->assertCount(2, $orders);
        $this->assertSame(2, $orders[0]->getId());
        $this->assertSame(3, $orders[1]->getId());
    }

    public function testWithCustomConnection(): void
    {
        $db = $this->createConnection();

        ConnectionProvider::set($db, 'custom');
        DbHelper::loadFixture($db);

        $customer = new CustomerWithCustomConnection();

        $this->assertSame($this->db(), $customer->db());

        $customer = $customer->withConnectionName('custom');

        $this->assertSame($db, $customer->db());

        $db->close();

        ConnectionProvider::remove('custom');
    }

    public function testWithFactory(): void
    {
        $factory = $this->createFactory();

        $orderQuery = new ActiveQuery($factory->create(OrderWithFactory::class)->withFactory($factory));
        $order = $orderQuery->with('customerWithFactory')->findByPk(2);

        $this->assertInstanceOf(OrderWithFactory::class, $order);
        $this->assertTrue($order->isRelationPopulated('customerWithFactory'));
        $this->assertInstanceOf(CustomerWithFactory::class, $order->getCustomerWithFactory());
    }

    public function testWithFactoryInstanceRelation(): void
    {
        $factory = $this->createFactory();

        $orderQuery = new ActiveQuery($factory->create(OrderWithFactory::class)->withFactory($factory));
        $order = $orderQuery->findByPk(2);

        $this->assertInstanceOf(OrderWithFactory::class, $order);
        $this->assertInstanceOf(CustomerWithFactory::class, $order->getCustomerWithFactoryInstance());
    }

    public function testWithFactoryRelationWithoutFactory(): void
    {
        $factory = $this->createFactory();

        $orderQuery = new ActiveQuery($factory->create(OrderWithFactory::class)->withFactory($factory));
        $order = $orderQuery->findByPk(2);

        $this->assertInstanceOf(OrderWithFactory::class, $order);
        $this->assertInstanceOf(Customer::class, $order->getCustomer());
    }

    public function testWithFactoryLazyRelation(): void
    {
        $factory = $this->createFactory();

        $orderQuery = new ActiveQuery($factory->create(OrderWithFactory::class)->withFactory($factory));
        $order = $orderQuery->findByPk(2);

        $this->assertInstanceOf(OrderWithFactory::class, $order);
        $this->assertFalse($order->isRelationPopulated('customerWithFactory'));
        $this->assertInstanceOf(CustomerWithFactory::class, $order->getCustomerWithFactory());
    }

    public function testWithFactoryWithConstructor(): void
    {
        $factory = $this->createFactory();

        $customerQuery = new ActiveQuery($factory->create(CustomerWithFactory::class));
        $customer = $customerQuery->findByPk(2);

        $this->assertInstanceOf(CustomerWithFactory::class, $customer);
        $this->assertFalse($customer->isRelationPopulated('ordersWithFactory'));
        $this->assertInstanceOf(OrderWithFactory::class, $customer->getOrdersWithFactory()[0]);
    }

    public function testWithFactoryNonInitiated(): void
    {
        $orderQuery = OrderWithFactory::query();
        $order = $orderQuery->findByPk(2);

        $customer = $order->getCustomer();

        $this->assertInstanceOf(Customer::class, $customer);

        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage('Too few arguments to function');

        $customer = $order->getCustomerWithFactory();
    }

    public function testSerialization(): void
    {
        $profile = new Profile();

        $this->assertEquals(
            "O:53:\"Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Profile\":3:{s:52:\"\0Yiisoft\ActiveRecord\AbstractActiveRecord\0oldValues\";N;s:50:\"\0Yiisoft\ActiveRecord\AbstractActiveRecord\0related\";a:0:{}s:64:\"\0Yiisoft\ActiveRecord\AbstractActiveRecord\0relationsDependencies\";a:0:{}}",
            serialize($profile)
        );

        $profileQuery = Profile::query();
        $profile = $profileQuery->findByPk(1);

        $this->assertEquals(
            "O:53:\"Yiisoft\ActiveRecord\Tests\Stubs\ActiveRecord\Profile\":5:{s:52:\"\0Yiisoft\ActiveRecord\AbstractActiveRecord\0oldValues\";a:2:{s:2:\"id\";i:1;s:11:\"description\";s:18:\"profile customer 1\";}s:50:\"\0Yiisoft\ActiveRecord\AbstractActiveRecord\0related\";a:0:{}s:64:\"\0Yiisoft\ActiveRecord\AbstractActiveRecord\0relationsDependencies\";a:0:{}s:5:\"\0*\0id\";i:1;s:14:\"\0*\0description\";s:18:\"profile customer 1\";}",
            serialize($profile)
        );
    }

    public function testRelationViaJson(): void
    {
        if (in_array($this->db()->getDriverName(), ['oci', 'sqlsrv'], true)) {
            $this->markTestSkipped('Oracle and MSSQL drivers do not support JSON columns.');
        }

        $promotionQuery = Promotion::query();
        /** @var Promotion[] $promotions */
        $promotions = $promotionQuery->with('itemsViaJson')->all();

        $this->assertSame([1, 2], ArArrayHelper::getColumn($promotions[0]->getItemsViaJson(), 'id'));
        $this->assertSame([3, 4, 5], ArArrayHelper::getColumn($promotions[1]->getItemsViaJson(), 'id'));
        $this->assertSame([1, 3], ArArrayHelper::getColumn($promotions[2]->getItemsViaJson(), 'id'));
        $this->assertCount(0, $promotions[3]->getItemsViaJson());

        /** Test inverse relation */
        foreach ($promotions as $promotion) {
            foreach ($promotion->getItemsViaJson() as $item) {
                $this->assertTrue($item->isRelationPopulated('promotionsViaJson'));
            }
        }

        $this->assertSame([1, 3], ArArrayHelper::getColumn($promotions[0]->getItemsViaJson()[0]->getPromotionsViaJson(), 'id'));
        $this->assertSame([1], ArArrayHelper::getColumn($promotions[0]->getItemsViaJson()[1]->getPromotionsViaJson(), 'id'));
        $this->assertSame([2, 3], ArArrayHelper::getColumn($promotions[1]->getItemsViaJson()[0]->getPromotionsViaJson(), 'id'));
        $this->assertSame([2], ArArrayHelper::getColumn($promotions[1]->getItemsViaJson()[1]->getPromotionsViaJson(), 'id'));
        $this->assertSame([2], ArArrayHelper::getColumn($promotions[1]->getItemsViaJson()[2]->getPromotionsViaJson(), 'id'));
        $this->assertSame([1, 3], ArArrayHelper::getColumn($promotions[2]->getItemsViaJson()[0]->getPromotionsViaJson(), 'id'));
        $this->assertSame([2, 3], ArArrayHelper::getColumn($promotions[2]->getItemsViaJson()[1]->getPromotionsViaJson(), 'id'));
    }

    public function testLazyRelationViaJson(): void
    {
        if (in_array($this->db()->getDriverName(), ['oci', 'sqlsrv'], true)) {
            $this->markTestSkipped('Oracle and MSSQL drivers do not support JSON columns.');
        }

        $itemQuery = Item::query();
        /** @var Item[] $items */
        $items = $itemQuery->all();

        $this->assertFalse($items[0]->isRelationPopulated('promotionsViaJson'));
        $this->assertFalse($items[1]->isRelationPopulated('promotionsViaJson'));
        $this->assertFalse($items[2]->isRelationPopulated('promotionsViaJson'));
        $this->assertFalse($items[3]->isRelationPopulated('promotionsViaJson'));
        $this->assertFalse($items[4]->isRelationPopulated('promotionsViaJson'));

        $this->assertSame([1, 3], ArArrayHelper::getColumn($items[0]->getPromotionsViaJson(), 'id'));
        $this->assertSame([1], ArArrayHelper::getColumn($items[1]->getPromotionsViaJson(), 'id'));
        $this->assertSame([2, 3], ArArrayHelper::getColumn($items[2]->getPromotionsViaJson(), 'id'));
        $this->assertSame([2], ArArrayHelper::getColumn($items[3]->getPromotionsViaJson(), 'id'));
        $this->assertSame([2], ArArrayHelper::getColumn($items[4]->getPromotionsViaJson(), 'id'));
    }

    public function testIsChanged(): void
    {
        $itemQuery = Item::query();
        $item = $itemQuery->findByPk(1);

        $this->assertFalse($item->isChanged());

        $item->set('name', 'New name');

        $this->assertTrue($item->isChanged());

        $newItem = new Item();

        $this->assertFalse($newItem->isChanged());

        $newItem->set('name', 'New name');

        $this->assertTrue($newItem->isChanged());

        $this->assertTrue((new Customer())->isChanged());
    }

    public static function dataUpsert(): array
    {
        return [
            'insert values' => [
                'values' => [
                    'email' => 'user4@example.com',
                    'name' => 'user4',
                    'address' => 'address4',
                ],
                'insertProperties' => null,
                'updateProperties' => false,
                'expected' => [
                    'id' => 4,
                    'email' => 'user4@example.com',
                    'name' => 'user4',
                    'address' => 'address4',
                ],
            ],
            'insert part values' => [
                'values' => [
                    'email' => 'user4@example.com',
                    'name' => 'user4',
                    'address' => 'address4',
                ],
                'insertProperties' => [
                    'email',
                    'name',
                ],
                'updateProperties' => false,
                'expected' => [
                    'id' => 4,
                    'email' => 'user4@example.com',
                    'name' => 'user4',
                    'address' => 'address4',
                ],
                'expectedAfterRefresh' => [
                    'id' => 4,
                    'email' => 'user4@example.com',
                    'name' => 'user4',
                    'address' => null,
                ],
            ],
            'insert from insertProperties' => [
                'values' => [
                    'email' => 'user4@example.com',
                    'address' => 'address4',
                ],
                'insertProperties' => [
                    'email',
                    'name' => 'user4',
                ],
                'updateProperties' => false,
                'expected' => [
                    'id' => 4,
                    'email' => 'user4@example.com',
                    'name' => 'user4',
                    'address' => 'address4',
                ],
                'expectedAfterRefresh' => [
                    'id' => 4,
                    'email' => 'user4@example.com',
                    'name' => 'user4',
                    'address' => null,
                ],
            ],
            'without changes' => [
                'values' => [
                    'email' => 'user3@example.com',
                    'name' => 'new name',
                ],
                'insertProperties' => null,
                'updateProperties' => false,
                'expected' => [
                    'id' => 3,
                    'email' => 'user3@example.com',
                    'name' => 'user3',
                    'address' => 'address3',
                ],
            ],
            'update from values' => [
                'values' => [
                    'email' => 'user3@example.com',
                    'name' => 'new name',
                    'address' => 'new address',
                ],
                'insertProperties' => null,
                'updateProperties' => true,
                'expected' => [
                    'id' => 3,
                    'email' => 'user3@example.com',
                    'name' => 'new name',
                    'address' => 'new address',
                ],
            ],
            'update from updateProperties' => [
                'values' => [
                    'email' => 'user3@example.com',
                    'address' => 'new address',
                ],
                'insertProperties' => null,
                'updateProperties' => [
                    'name' => 'another name',
                    'address' => 'another address',
                ],
                'expected' => [
                    'id' => 3,
                    'email' => 'user3@example.com',
                    'name' => 'another name',
                    'address' => 'another address',
                ],
            ],
            'update partly from updateProperties' => [
                'values' => [],
                'insertProperties' => [
                    'email' => 'user3@example.com',
                    'name' => 'new name',
                    'address' => 'new address',
                    'status' => 10,
                ],
                'updateProperties' => [
                    'name' => 'another name',
                    'address',
                    'bool_status' => true,
                ],
                'expected' => [
                    'id' => 3,
                    'email' => 'user3@example.com',
                    'name' => 'another name',
                    'address' => 'new address',
                    'status' => 2,
                    'bool_status' => true,
                ],
            ],
            'update with same string key and int value' => [
                'values' => [
                    'address' => 'old address',
                ],
                'insertProperties' => [
                    'email' => 'user3@example.com',
                    'address' => 'insert address',
                ],
                'updateProperties' => [
                    'address',
                    'address' => 'update address',
                ],
                'expected' => [
                    'id' => 3,
                    'email' => 'user3@example.com',
                    'address' => 'insert address',
                ],
            ],
        ];
    }

    #[DataProvider('dataUpsert')]
    public function testUpsert(
        array $values,
        array|null $insertProperties,
        array|bool $updateProperties,
        array $expected,
        array|null $expectedAfterRefresh = null,
    ): void {
        $this->reloadFixtureAfterTest();

        $customer = new Customer();

        foreach ($values as $property => $value) {
            $customer->set($property, $value);
        }

        $this->assertTrue($customer->upsert($insertProperties, $updateProperties));

        $this->assertFalse($customer->isNewRecord());

        foreach ($expected as $property => $value) {
            $this->assertSame($value, $customer->get($property));
        }

        $customer->refresh();

        foreach ($expectedAfterRefresh ?? $expected as $property => $value) {
            $this->assertSame($value, $customer->get($property));
        }
    }

    public function testUpsertWithException(): void
    {
        $customer = Customer::findByPk(1);

        $this->expectException(InvalidCallException::class);
        $this->expectExceptionMessage('The record is not new and cannot be inserted.');

        $customer->upsert();
    }

    public function testTimestampBehavior(): void
    {
        $this->reloadFixtureAfterTest();

        $startTime = time();

        $order = new Order();

        $order->setCustomerId(1);
        $order->setTotal(0);
        $order->save();

        $insertTime = time();

        $createdAt = $order->getCreatedAt()->getTimestamp();
        $updatedAt = $order->getUpdatedAt()->getTimestamp();

        $this->assertEqualsWithDelta($insertTime, $createdAt, $insertTime - $startTime);
        $this->assertEqualsWithDelta($insertTime, $updatedAt, $insertTime - $startTime);
        $this->assertEqualsWithDelta($createdAt, $updatedAt, 1);

        $order->setTotal(1);

        sleep(1); // Ensure that the updatedAt timestamp will be different
        $order->save();

        $newUpdatedAt = $order->getUpdatedAt()->getTimestamp();

        $this->assertSame($createdAt, $order->getCreatedAt()->getTimestamp());
        $this->assertTrue($updatedAt < $newUpdatedAt);

        $order->refresh();

        $this->assertSame($createdAt, $order->getCreatedAt());
        $this->assertSame($newUpdatedAt, $order->getUpdatedAt());
    }

    #[TestWith(['Sergei', 1])]
    #[TestWith(['unknown', 2])]
    public function testDefaultValue(string $expected, int $id): void
    {
        $record = DefaultValueAr::query()->findByPk($id);
        $this->assertSame($expected, $record->name);
    }

    #[TestWith(['Sergei', 'Sergei'])]
    #[TestWith(['Vasya', null])]
    public function testDefaultValueOnInsertSave(string $expected, ?string $value): void
    {
        $this->reloadFixtureAfterTest();

        $record = new DefaultValueOnInsertAr();
        $record->id = 99;
        $record->name = $value;
        $record->save();

        $this->assertSame($expected, $record->name);

        $record = DefaultValueOnInsertAr::query()->findByPk($record->id);
        $this->assertSame($expected, $record->name);
    }

    #[TestWith(['Kesha', 'Kesha'])]
    #[TestWith(['Vasya', null])]
    public function testDefaultValueOnInsertUpsert(string $expected, ?string $value): void
    {
        $this->reloadFixtureAfterTest();

        $record = new DefaultValueOnInsertAr();
        $record->id = 1;
        $record->name = $value;
        $record->upsert();

        $this->assertSame($expected, $record->name);

        $record = DefaultValueOnInsertAr::query()->findByPk(1);
        $this->assertSame($expected, $record->name);
    }

    public function testSetValueOnUpdateSave(): void
    {
        $this->reloadFixtureAfterTest();

        $record = SetValueOnUpdateAr::query()->findByPk(1);
        $record->save();

        $this->assertSame('Updated', $record->name);

        $record = SetValueOnUpdateAr::query()->findByPk($record->id);
        $this->assertSame('Updated', $record->name);
    }

    public function testSetValueOnUpdateUpsert(): void
    {
        $this->reloadFixtureAfterTest();

        $record = new SetValueOnUpdateAr();
        $record->id = 1;
        $record->name = 'Kesha';
        $record->upsert();
        $this->assertSame('Updated', $record->name);

        $record = SetValueOnUpdateAr::query()->findByPk($record->id);
        $this->assertSame('Updated', $record->name);

        $record = new SetValueOnUpdateAr();
        $record->id = 1;
        $record->upsert(updateProperties: ['name' => 'Kesha']);
        $this->assertSame('Updated', $record->name);

        $record = new SetValueOnUpdateAr();
        $record->id = 1;
        $record->upsert(updateProperties: false);
        $this->assertSame('Updated', $record->name);
    }

    public function testStopPropagation(): void
    {
        $this->reloadFixtureAfterTest();

        $category = StopPropagation\Category::query()->findByPk(1);
        $category->name = 'Test';
        $category->save();

        $this->assertSame('Test', $category->name);
    }

    public function testAfterDelete(): void
    {
        $this->reloadFixtureAfterTest();

        EventDispatcherProvider::set(
            CategoryAfterDelete::class,
            new SimpleEventDispatcher(
                static function(object $event) {
                    if ($event instanceof AfterDelete) {
                        $event->model->isDeleted = true;
                    }
                }
            )
        );
        $category = CategoryAfterDelete::query()->findByPk(1);
        $category->delete();

        $this->assertTrue($category->isDeleted);
    }

    public function testLinkViaRelationWithNewRecord(): void
    {
        $customer = new Customer();
        $item = new Item();

        $this->expectException(InvalidCallException::class);
        $this->expectExceptionMessage(
            'Unable to link models: the models being linked cannot be newly created.'
        );
        $customer->link('items2', $item);
    }

    public function testLinkViaTable(): void
    {
        $this->reloadFixtureAfterTest();

        $customer = Customer::query()->findByPk(1);
        $this->assertNotNull($customer);

        $item = Item::query()->findByPk(3);
        $this->assertNotNull($item);

        $customer->link('orderItems', $item, ['quantity' => 5, 'subtotal' => 50.00]);

        $orderItems = $customer->relation('orderItems');
        $itemIds = ArArrayHelper::getColumn($orderItems, 'id');

        $this->assertContains(3, $itemIds);
    }

    public function testLinkBothNewRecordsWithSharedPrimaryKey(): void
    {
        $user = new User();
        $user->setId(100);
        $user->setUsername('testuser');

        $profile = new UserProfile();
        $profile->setId(100);
        $profile->setBio('Test bio');

        $this->expectException(InvalidCallException::class);
        $this->expectExceptionMessage(
            'Unable to link models: at most one model can be newly created.'
        );

        $user->link('profile', $profile);
    }

    public function testLinkNewRecordToExistingWithSharedPrimaryKey(): void
    {
        $this->reloadFixtureAfterTest();

        $user = User::query()->findByPk(1);

        $profile = new UserProfile();
        $profile->setBio('Bio for existing user');

        $profile->link('user', $user);

        $this->assertEquals(1, $profile->getId());
        $this->assertFalse($profile->isNewRecord());
    }
}
