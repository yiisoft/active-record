<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Tests\Support;

use Yiisoft\Db\Driver\Pdo\PdoConnectionInterface;

use function dirname;
use function explode;
use function file_get_contents;
use function preg_replace;
use function str_replace;
use function trim;

final class DbHelper
{
    /**
     * Loads the fixture into the database.
     */
    public static function loadFixture(PdoConnectionInterface $db): void
    {
        $driverName = $db->getDriverName();

        $fixture = match ($driverName) {
            'mysql' => dirname(__DIR__) . '/data/mysql.sql',
            'oci' => dirname(__DIR__) . '/data/oci.sql',
            'pgsql' => dirname(__DIR__) . '/data/pgsql.sql',
            'sqlite' => dirname(__DIR__) . '/data/sqlite.sql',
            'sqlsrv' => dirname(__DIR__) . '/data/mssql.sql',
        };

        if ($db->isActive()) {
            $db->close();
        }

        $db->open();

        if ($driverName === 'oci') {
            [$drops, $creates] = explode('/* STATEMENTS */', file_get_contents($fixture), 2);
            [$statements, $triggers, $data] = explode('/* TRIGGERS */', $creates, 3);
            $lines = array_merge(
                explode('--', $drops),
                explode(';', $statements),
                explode('/', $triggers),
                explode(';', $data)
            );
        } else {
            $lines = explode(';', file_get_contents($fixture));
        }

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $db->getPDO()->exec($line);
            }
        }
    }

    /**
     * Adjust dbms specific escaping.
     *
     * @param string $sql string SQL statement to adjust.
     * @param string $driverName string DBMS name.
     */
    public static function replaceQuotes(string $sql, string $driverName): string
    {
        return match ($driverName) {
            'mysql' => str_replace(['[[', ']]'], '`', $sql),
            'oci', 'sqlite' => str_replace(['[[', ']]'], '"', $sql),
            'pgsql' => str_replace(['\\[', '\\]'], ['[', ']'], preg_replace('/(\[\[)|((?<!(\[))]])/', '"', $sql)),
            'db', 'sqlsrv' => str_replace(['[[', ']]'], ['[', ']'], $sql),
            default => $sql,
        };
    }
}
