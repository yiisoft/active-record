<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px" alt="Yii">
    </a>
    <h1 align="center">Yii ActiveRecord</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiisoft/active-record/v)](https://packagist.org/packages/yiisoft/active-record)
[![Total Downloads](https://poser.pugx.org/yiisoft/active-record/downloads)](https://packagist.org/packages/yiisoft/active-record)
[![codecov](https://codecov.io/gh/yiisoft/active-record/branch/master/graph/badge.svg?token=w4KarhYyEF)](https://codecov.io/gh/yiisoft/active-record)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Factive-record%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/active-record/master)
[![static analysis](https://github.com/yiisoft/active-record/actions/workflows/static.yml/badge.svg?branch=dev)](https://github.com/yiisoft/active-record/actions/workflows/static.yml)
[![type-coverage](https://shepherd.dev/github/yiisoft/active-record/coverage.svg)](https://shepherd.dev/github/yiisoft/active-record)

This package provides [ActiveRecord] library.
It is used in [Yii Framework] but is supposed to be usable separately.

[ActiveRecord]: https://en.wikipedia.org/wiki/Active_record_pattern
[Yii Framework]: https://www.yiiframework.com/

## Support databases

| Packages                                            | Versions        | CI-Actions |
|-----------------------------------------------------|-----------------|------------|
| [[db-mssql]](https://github.com/yiisoft/db-mssql)   | **2017 - 2022** |[![Build status](https://github.com/yiisoft/db-mssql/workflows/build/badge.svg)](https://github.com/yiisoft/db-mssql/actions?query=workflow%3Abuild) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fdb-mssql%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/db-mssql/master) [![codecov](https://codecov.io/gh/yiisoft/db-mssql/branch/master/graph/badge.svg?token=UF9VERNMYU)](https://codecov.io/gh/yiisoft/db-mssql)|
| [[db-mysql]](https://github.com/yiisoft/db-mysql)   | **5.7 - 8.0**   |[![Build status](https://github.com/yiisoft/db-mysql/workflows/build/badge.svg)](https://github.com/yiisoft/db-mysql/actions?query=workflow%3Abuild) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fdb-mysql%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/db-mysql/master) [![codecov](https://codecov.io/gh/yiisoft/db-mysql/branch/master/graph/badge.svg?token=gsKVx3WQt4)](https://codecov.io/gh/yiisoft/db-mysql)|
| [[db-oracle]](https://github.com/yiisoft/db-oracle) | **12 - 21**     |[![Build status](https://github.com/yiisoft/db-oracle/workflows/build/badge.svg)](https://github.com/yiisoft/db-oracle/actions?query=workflow%3Abuild) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fdb-oracle%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/db-oracle/master) [![codecov](https://codecov.io/gh/yiisoft/db-oracle/branch/master/graph/badge.svg?token=XGJAFXVHSH)](https://codecov.io/gh/yiisoft/db-oracle)|
| [[db-pgsql]](https://github.com/yiisoft/db-pgsql)   | **9.0 - 16.0**  |[![Build status](https://github.com/yiisoft/db-pgsql/workflows/build/badge.svg)](https://github.com/yiisoft/db-pgsql/actions?query=workflow%3Abuild) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fdb-pgsql%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/db-pgsql/master) [![codecov](https://codecov.io/gh/yiisoft/db-pgsql/branch/master/graph/badge.svg?token=3FGN91IVZA)](https://codecov.io/gh/yiisoft/db-pgsql)|
| [[db-sqlite]](https://github.com/yiisoft/db-sqlite) | **3:latest**    |[![Build status](https://github.com/yiisoft/db-sqlite/workflows/build/badge.svg)](https://github.com/yiisoft/db-sqlite/actions?query=workflow%3Abuild) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fdb-sqlite%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/db-sqlite/master) [![codecov](https://codecov.io/gh/yiisoft/db-sqlite/branch/master/graph/badge.svg?token=YXUHCPPITH)](https://codecov.io/gh/yiisoft/db-sqlite)|

## Requirements

- PHP 8.1 - 8.4.

## Installation

The package could be installed with [Composer](https://getcomposer.org):

```shell
composer require yiisoft/active-record
```

**Note: You must install the repository of the implementation to use.**

Example:

```shell
composer require yiisoft/db-sqlite
```

## Configure container with database connection

Add the following code to the configuration files, for example:

`config/common/di/db.php`:

```php
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;
use Yiisoft\Db\Sqlite\Driver;

return [
    ConnectionInterface::class => [
        'class' => Connection::class,
        '__construct()' => [
            'driver' => new Driver($params['yiisoft/db-sqlite']['dsn']),
        ],
    ]
];
```

`config/common/params.php`:

```php
return [
    'yiisoft/db-sqlite' => [
        'dsn' => 'sqlite:' . dirname(__DIR__) . '/runtime/yiitest.sq3',
    ]
]
```

For more information about how to configure the connection, follow [Yii Database](https://github.com/yiisoft/db/blob/master/docs/guide/en/README.md).

`config/common/bootstrap.php`:

```php
use Psr\Container\ContainerInterface;
use Yiisoft\ActiveRecord\ConnectionProvider;
use Yiisoft\Db\Connection\ConnectionInterface;

return [
    static function (ContainerInterface $container): void {
        ConnectionProvider::set($container->get(ConnectionInterface::class));
    }
];
```

Be sure to include `bootstrap.php` file using `config-plugin` in `extra` section of `composer.json` (see [yiisoft/config](https://github.com/yiisoft/config)):

```
"extra": {
    "config-plugin": {
        "bootstrap": "common/bootstrap.php"
    }
}
```

Or if you use `config-plugin-file` in `extra` section of `composer.json` as follows:

```
"extra": {
    "config-plugin-file": "configuration.php"
}
```

add the line `'bootstrap' => 'common/bootstrap.php',` to `configuration.php` file:

```php
return [
    'config-plugin' => [
        'bootstrap' => 'common/bootstrap.php',
    ],
];
```

See other ways to [define the DB connection](docs/define-connection.md) for Active Record.

## Defined your active record class

```php
use Yiisoft\ActiveRecord\ActiveRecord;

/**
 * Entity User.
 *
 * Database fields:
 * @property int $id
 * @property string $username
 * @property string $email
 **/
#[\AllowDynamicProperties]
final class User extends ActiveRecord
{
    public function tableName(): string
    {
        return '{{%user}}';
    }
}
```

For more information, follow [Create Active Record Model](docs/create-model.md).

## Usage

Now you can use the Active Record:

```php
use App\Entity\User;

$user = new User();
$user->set('username', 'yiiliveext');
$user->set('email', 'yiiliveext@mail.ru');
$user->save();
```

Using with `ActiveRecord::query()`:

```php
use App\Entity\User;
use Yiisoft\ActiveRecord\ActiveQuery;

$user = User::query()->where(['id' => 1])->one();

$username = $user->get('username');
$email = $user->get('email');
```

## Documentation

- [Optimistic Locking](docs/optimistic-locking.md)
- [Internals](docs/internals.md)

If you need help or have a question, the [Yii Forum](https://forum.yiiframework.com/c/yii-3-0/63) is a good place for that.
You may also check out other [Yii Community Resources](https://www.yiiframework.com/community).

## License

The Yii Active Record Library is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)
