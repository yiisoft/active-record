<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px" alt="Yii">
    </a>
    <h1 align="center">Yii Active Record</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiisoft/active-record/v)](https://packagist.org/packages/yiisoft/active-record)
[![Total Downloads](https://poser.pugx.org/yiisoft/active-record/downloads)](https://packagist.org/packages/yiisoft/active-record)
[![Code Coverage](https://codecov.io/gh/yiisoft/active-record/branch/master/graph/badge.svg)](https://codecov.io/gh/yiisoft/active-record)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Factive-record%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/active-record/master)
[![Static analysis](https://github.com/yiisoft/active-record/actions/workflows/static.yml/badge.svg?branch=master)](https://github.com/yiisoft/active-record/actions/workflows/static.yml?query=branch%3Amaster)
[![type-coverage](https://shepherd.dev/github/yiisoft/active-record/coverage.svg)](https://shepherd.dev/github/yiisoft/active-record)
[![psalm-level](https://shepherd.dev/github/yiisoft/active-record/level.svg)](https://shepherd.dev/github/yiisoft/active-record)

This package provides [Active Record pattern](https://en.wikipedia.org/wiki/Active_record_pattern) implementation.

Supported databases:

| Packages                                                   | Build status                                                                                                                                                                                                        |
|------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [Microsft SQL Server](https://github.com/yiisoft/db-mssql) | [![Build status](https://github.com/yiisoft/active-record/actions/workflows/db-mssql.yml/badge.svg?branch=master)](https://github.com/yiisoft/active-record/actions/workflows/db-mssql.yml?query=branch%3Amaster)   |
| [MySQL](https://github.com/yiisoft/db-mysql)               | [![Build status](https://github.com/yiisoft/active-record/actions/workflows/db-mysql.yml/badge.svg?branch=master)](https://github.com/yiisoft/active-record/actions/workflows/db-mysql.yml?query=branch%3Amaster)   |
| [Oracle](https://github.com/yiisoft/db-oracle)             | [![Build status](https://github.com/yiisoft/active-record/actions/workflows/db-oracle.yml/badge.svg?branch=master)](https://github.com/yiisoft/active-record/actions/workflows/db-oracle.yml?query=branch%3Amaster) |
| [PostgreSQL](https://github.com/yiisoft/db-pgsql)          | [![Build status](https://github.com/yiisoft/active-record/actions/workflows/db-pgsql.yml/badge.svg?branch=master)](https://github.com/yiisoft/active-record/actions/workflows/db-pgsql.yml?query=branch%3Amaster)   |
| [SQLite](https://github.com/yiisoft/db-sqlite)             | [![Build status](https://github.com/yiisoft/active-record/actions/workflows/db-sqlite.yml/badge.svg?branch=master)](https://github.com/yiisoft/active-record/actions/workflows/db-sqlite.yml?query=branch%3Amaster) |

## Requirements

- PHP 8.1 - 8.5.

## Installation

The package could be installed with [Composer](https://getcomposer.org):

```shell
composer require yiisoft/active-record
```

> [!IMPORTANT]
> See also [installation notes](https://github.com/yiisoft/db/?tab=readme-ov-file#documentation) for `yiisoft/db`
> package.

After installing `yiisoft/active-record`, you also need to configure a database connection:

1. Configure the connection, follow [Yii Database](https://github.com/yiisoft/db/blob/master/docs/guide/en/README.md)
guide.
2.  [Define the Database Connection for Active Record](docs/define-connection.md)

## General usage

Defined your active record class (for more information, follow [Create Active Record Model](docs/create-model.md) guide):

```php
/**
 * Entity User.
 *
 * Database fields:
 * @property int $id
 * @property string $username
 * @property string $email
 **/
#[\AllowDynamicProperties]
final class User extends \Yiisoft\ActiveRecord\ActiveRecord
{
    public function tableName(): string
    {
        return '{{%user}}';
    }
}
```

Now you can use the active record:

```php
// Creating a new record
$user = new User();
$user->set('username', 'alexander-pushkin');
$user->set('email', 'pushkin@example.com');
$user->save();

// Retrieving a record
$user = User::query()->findByPk(1);

// Read properties
$username = $user->get('username');
$email = $user->get('email');
```

## Documentation

- [Define the Database Connection for Active Record](docs/define-connection.md)
- [Create Active Record Model](docs/create-model.md)
- [Define Active Record Relations](docs/define-relations.md)
- [Extending Functionality With Traits](docs/traits/traits.md)
- [Using Dependency Injection With Active Record Model](docs/using-di.md)
- [Optimistic Locking](docs/optimistic-locking.md)
- [Internals](docs/internals.md)

If you need help or have a question, the [Yii Forum](https://forum.yiiframework.com/c/yii-3-0/63) is a good place
for that. You may also check out other [Yii Community Resources](https://www.yiiframework.com/community).

## License

The Yii Active Record is free software. It is released under the terms of the BSD License.
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
