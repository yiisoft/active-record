<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

interface TransactionalInterface
{
    /**
     * The insert operation. This is mainly used when overriding {@see transactions()} to specify which operations are
     * transactional.
     *
     * @var int
     *
     * @psalm-suppress MissingClassConstType
     */
    public const OP_INSERT = 0x01;

    /**
     * The update operation. This is mainly used when overriding {@see transactions()} to specify which operations are
     * transactional.
     *
     * @var int
     *
     * @psalm-suppress MissingClassConstType
     */
    public const OP_UPDATE = 0x02;

    /**
     * The delete operation. This is mainly used when overriding {@see transactions()} to specify which operations are
     * transactional.
     *
     * @var int
     *
     * @psalm-suppress MissingClassConstType
     */
    public const OP_DELETE = 0x04;

    /**
     * Returns a value indicating whether the specified operation is transactional.
     *
     * @param int $operation The operation to check. Possible values are {@see OP_INSERT}, {@see OP_UPDATE} and
     * {@see OP_DELETE}.
     *
     * @return bool Whether the specified operation is transactional.
     */
    public function isTransactional(int $operation): bool;

    /**
     * Declares which DB operations should be performed within a transaction in different scenarios.
     *
     * The supported DB operations are: {@see OP_INSERT}, {@see OP_UPDATE} and {@see OP_DELETE}, which correspond to the
     * {@see insert()}, {@see update()} and {@see delete()} methods, respectively.
     *
     * By default, these methods are NOT enclosed in a DB transaction.
     *
     * In some scenarios, to ensure data consistency, you may want to enclose some or all of them in transactions. You
     * can do so by overriding this method and returning the operations that need to be transactional. For example,
     *
     * ```php
     * return [
     *     'admin' => self::OP_INSERT,
     *     'api' => self::OP_INSERT | self::OP_UPDATE | self::OP_DELETE,
     *     // the above is equivalent to the following:
     *     // 'api' => self::OP_ALL,
     *
     * ];
     * ```
     *
     * The above declaration specifies that in the "admin" scenario, the insert operation ({@see insert()}) should be
     * done in a transaction; and in the "api" scenario, all the operations should be done in a transaction.
     *
     * @return array The declarations of transactional operations. The array keys are scenarios names, and the array
     * values are the corresponding transaction operations.
     */
    public function transactions(): array;
}
