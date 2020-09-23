<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Yiisoft\Db\Data\DataProvider;
use Yiisoft\Db\Exception\InvalidConfigException;
use Yiisoft\Db\Query\QueryInterface;

use function array_keys;
use function call_user_func;
use function count;
use function is_string;

/**
 * ActiveDataProvider implements a data provider based on {@see \Yiisoft\Db\Query\Query} and
 * {@see \Yiisoft\ActiveRecord\ActiveQuery}.
 *
 * ActiveDataProvider provides data by performing DB queries using {@see query}.
 *
 * The following is an example of using ActiveDataProvider to provide ActiveRecord instances:
 *
 * ```php
 *    $provider = new ActiveDataProvider(
 *        $db, // connection db
 *        Order::find()->orderBy('id')
 *    );
 * ```
 *
 * And the following example shows how to use ActiveDataProvider without ActiveRecord:
 *
 * ```php
 *    $query = new Query($db);
 *
 *    $provider = new ActiveDataProvider(
 *        $db, // connection db
 *        $query->from('order')->orderBy('id')
 *    );
 * ```
 *
 * For more details and usage information on ActiveDataProvider, see the
 * [guide article on data providers](guide:output-data-providers).
 */
final class ActiveDataProvider extends DataProvider
{
    /**
     * @var QueryInterface|null the query that is used to fetch data models and {@see totalCount}
     *
     * if it is not explicitly set.
     */
    private ?QueryInterface $query = null;

    /**
     * @var string|callable the column that is used as the key of the data models.
     *
     * This can be either a column name, or a callable that returns the key value of a given data model.
     *
     * If this is not set, the following rules will be used to determine the keys of the data models:
     *
     * - If {@see query} is an {@see \Yiisoft\ActiveRecord\ActiveQuery} instance, the primary keys of
     * {@see \Yiisoft\ActiveRecord\ActiveQuery::modelClass} will be used.
     *
     * - Otherwise, the keys of the {@see models} array will be used.
     *
     * @see getKeys()
     */
    private $key;

    public function __construct(QueryInterface $query)
    {
        $this->query = $query;

        parent::__construct();
    }

    protected function prepareActiveRecord(): array
    {
        $query = $this->prepareQuery();

        if ($query->shouldEmulateExecution()) {
            return [];
        }

        return $query->all();
    }

    /**
     * Prepares the sql-query that will get the data for current page.
     *
     * @throws InvalidConfigException
     *
     * @return QueryInterface
     */
    public function prepareQuery(): QueryInterface
    {
        if (!$this->query instanceof QueryInterface) {
            throw new InvalidConfigException('The "query" property must be an instance of a class that implements the QueryInterface e.g. Yiisoft\Db\Query or its subclasses.');
        }
        $query = clone $this->query;
        if (($pagination = $this->getPagination()) !== false) {
            $pagination->totalCount = $this->getTotalCount();

            if ($pagination->totalCount === 0) {
                $query->emulateExecution();
            }

            $query->limit($pagination->getLimit())->offset($pagination->getOffset());
        }

        if (($sort = $this->getSort()) !== null) {
            $query->addOrderBy($sort->getOrders());
        }

        return $query;
    }

    /**
     * Prepares the keys associated with the currently available data models.
     *
     * @param array $models the available data models.
     *
     * @return array the keys.
     */
    protected function prepareKeys(array $models = []): array
    {
        $keys = [];

        if ($this->key !== null) {
            foreach ($models as $model) {
                if (is_string($this->key)) {
                    $keys[] = $model[$this->key];
                } else {
                    $keys[] = call_user_func($this->key, $model);
                }
            }

            return $keys;
        }

        if ($this->query instanceof ActiveQueryInterface) {
            $pks = $this->query->getARInstance()->primaryKey();

            if (count($pks) === 1) {
                $pk = $pks[0];
                foreach ($models as $model) {
                    $keys[] = $model[$pk];
                }
            } else {
                foreach ($models as $model) {
                    $kk = [];
                    foreach ($pks as $pk) {
                        $kk[$pk] = $model[$pk];
                    }
                    $keys[] = $kk;
                }
            }

            return $keys;
        }

        return array_keys($models);
    }

    /**
     * Prepares the data models that will be made available in the current page.
     *
     * @throws InvalidConfigException
     *
     * @return array the available data models.
     */
    protected function prepareModels(): array
    {
        if (!$this->query instanceof QueryInterface) {
            throw new InvalidConfigException(
                'The "query" property must be an instance of a class that implements the QueryInterface e.g.'
                    . '\Yiisoft\Db\Query\Query or its subclasses.'
            );
        }

        $query = clone $this->query;

        if (($pagination = $this->getPagination()) !== null) {
            $pagination->totalCount = $this->getTotalCount();
            if ($pagination->totalCount === 0) {
                return [];
            }

            $query->limit($pagination->getLimit())->offset($pagination->getOffset());
        }

        if (($sort = $this->getSort()) !== null) {
            $query->addOrderBy($sort->getOrders());
        }

        return $query->all();
    }

    /**
     * Returns a value indicating the total number of data models in this data provider.
     *
     * @throws InvalidConfigException
     *
     * @return int total number of data models in this data provider.
     */
    protected function prepareTotalCount(): int
    {
        if (!$this->query instanceof QueryInterface) {
            throw new InvalidConfigException(
                'The "query" property must be an instance of a class that implements the QueryInterface e.g. '
                . '\Yiisoft\Db\Query\Query or its subclasses.'
            );
        }

        $query = clone $this->query;

        return (int) $query->limit(-1)->offset(-1)->orderBy([])->count('*');
    }
}
