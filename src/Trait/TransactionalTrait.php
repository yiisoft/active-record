<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Trait;

use Throwable;
use Yiisoft\ActiveRecord\ActiveRecordInterface;
use Yiisoft\ActiveRecord\TransactionalInterface;

use function in_array;

/**
 * Trait to implement transactional operations and {@see TransactionalInterface} for ActiveRecord.
 *
 * @see ActiveRecordInterface::insert()
 * @see ActiveRecordInterface::update()
 * @see ActiveRecordInterface::delete()
 */
trait TransactionalTrait
{
    public function delete(): int
    {
        if (!$this->isTransactional(TransactionalInterface::OP_DELETE)) {
            return $this->deleteInternal();
        }

        $transaction = $this->db()->beginTransaction();

        try {
            $result = $this->deleteInternal();
            $transaction->commit();

            return $result;
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function insert(array $attributes = null): bool
    {
        if (!$this->isTransactional(TransactionalInterface::OP_INSERT)) {
            return $this->insertInternal($attributes);
        }

        $transaction = $this->db()->beginTransaction();

        try {
            $result = $this->insertInternal($attributes);
            if ($result === false) {
                $transaction->rollBack();
            } else {
                $transaction->commit();
            }

            return $result;
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    public function isTransactional(int $operation): bool
    {
        return in_array($operation, $this->transactions(), true);
    }

    public function transactions(): array
    {
        return [
            TransactionalInterface::OP_INSERT,
            TransactionalInterface::OP_UPDATE,
            TransactionalInterface::OP_DELETE,
        ];
    }

    public function update(array $attributeNames = null): int
    {
        if (!$this->isTransactional(TransactionalInterface::OP_UPDATE)) {
            return $this->updateInternal($attributeNames);
        }

        $transaction = $this->db()->beginTransaction();

        try {
            $result = $this->updateInternal($attributeNames);
            if ($result === 0) {
                $transaction->rollBack();
            } else {
                $transaction->commit();
            }

            return $result;
        } catch (Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }
}
