<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://avatars0.githubusercontent.com/u/993323" height="80px">
    </a>
    <h1 align="center">Yii ActiveRecord Library</h1>
    <br>
</p>

This package provides [ActiveRecord] library.
It is used in [Yii Framework] but is supposed to be usable separately.

[ActiveRecord]: https://en.wikipedia.org/wiki/Active_record_pattern
[Yii Framework]: https://www.yiiframework.com/

[![Latest Stable Version](https://poser.pugx.org/yiisoft/active-record/v/stable.png)](https://packagist.org/packages/yiisoft/active-record)
[![Total Downloads](https://poser.pugx.org/yiisoft/active-record/downloads.png)](https://packagist.org/packages/yiisoft/active-record)
[![Build status](https://github.com/yiisoft/active-record/workflows/build/badge.svg)](https://github.com/yiisoft/active-record/actions?query=workflow%3Abuild)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/active-record/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/active-record/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/active-record/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/active-record/?branch=master)
[![type-coverage](https://shepherd.dev/github/yiisoft/active-record/coverage.svg)](https://shepherd.dev/github/yiisoft/active-record)

## Support databases:

|Packages|  PHP | Mssql Version            |  CI-Actions
|:------:|:----:|:------------------------:|:-----------:|
|[[db-mssql]](https://github.com/yiisoft/db-mssql)|**7.4 - 8.0**| **2017 - 2019**|[![Build status](https://github.com/yiisoft/db-mssql/workflows/build/badge.svg)](https://github.com/yiisoft/db-mssql/actions?query=workflow%3Abuild) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fdb-mssql%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/db-mssql/master) [![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/db-mssql/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/db-mssql/?branch=master) [![type-coverage](https://shepherd.dev/github/yiisoft/db-mssql/coverage.svg)](https://shepherd.dev/github/yiisoft/db-mssql)|
|[[db-mysql]](https://github.com/yiisoft/db-mysql)|**7.4 - 8.0**| **5.7 - 8.0**|[![Build status](https://github.com/yiisoft/db-mysql/workflows/build/badge.svg)](https://github.com/yiisoft/db-mysql/actions?query=workflow%3Abuild) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fdb-mysql%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/db-mysql/master) [![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/db-mysql/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/db-mysql/?branch=master) [![type-coverage](https://shepherd.dev/github/yiisoft/db-mysql/coverage.svg)](https://shepherd.dev/github/yiisoft/db-mysql)|
|[[db-pgsql]](https://github.com/yiisoft/db-pgsql)|**7.4 - 8.0**| **9.0 - 13.0**|[![Build status](https://github.com/yiisoft/db-pgsql/workflows/build/badge.svg)](https://github.com/yiisoft/db-pgsql/actions?query=workflow%3Abuild) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fdb-pgsql%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/db-pgsql/master) [![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/db-pgsql/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/db-pgsql/?branch=master) [![type-coverage](https://shepherd.dev/github/yiisoft/db-pgsql/coverage.svg)](https://shepherd.dev/github/yiisoft/db-pgsql)
|[[db-sqlite]](https://github.com/yiisoft/db-sqlite)|**7.4 - 8.0**| **3:latest**|[![Build status](https://github.com/yiisoft/db-sqlite/workflows/build/badge.svg)](https://github.com/yiisoft/db-sqlite/actions?query=workflow%3Abuild) [![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fdb-sqlite%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/db-sqlite/master) [![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/db-sqlite/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/db-sqlite/?branch=master) [![type-coverage](https://shepherd.dev/github/yiisoft/db-sqlite/coverage.svg)](https://shepherd.dev/github/yiisoft/db-sqlite)


## Installation

The package could be installed via composer:

```php
composer require yiisoft/active-record
```

**Note: You must install the repository of the implementation to use.**

Example:

```php
composer require yiisoft/db-mysql
```

## Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```php
./vendor/bin/phpunit
```

Note: You must have SQLITE installed to run the tests, it supports all SQLITE version 3.

## Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework. To run it:

```php
./vendor/bin/infection
```

## Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/docs/). To run static analysis:

```php
./vendor/bin/psalm
```
