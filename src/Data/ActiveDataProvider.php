<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord\Data;

use Yiisoft\ActiveRecord\ActiveQueryInterface;
use Yiisoft\Db\Connection;
use yii\exceptions\InvalidConfigException;
use yii\base\Model;
use Yiisoft\Data\BaseDataProvider;
use Yiisoft\Db\ConnectionInterface;
use Yiisoft\Db\QueryInterface;

/**
 * ActiveDataProvider implements a data provider based on [[\Yiisoft\Db\Query]] and [[\Yiisoft\ActiveRecord\ActiveQuery]].
 *
 * ActiveDataProvider provides data by performing DB queries using [[query]].
 *
 * The following is an example of using ActiveDataProvider to provide ActiveRecord instances:
 *
 * ```php
 * $provider = new ActiveDataProvider(
 *      Yii::$app->db,
 *      Post::find()
 * );
 * $provider->pagination' => [
 *     'pageSize' => 20,
 * ];
 *
 *
 * // get the posts in the current page
 * $posts = $provider->getModels(); // or $provider->models
 * ```
 *
 * And the following example shows how to use ActiveDataProvider without ActiveRecord:
 *
 * ```php
 * $query = new Query();
 * $provider = new ActiveDataProvider(
 *     Yii::get('db'),
 *     $query->from('post')
 * );
 * $provider->pagination' => [
 *     'pageSize' => 20,
 * ];
 *
 *
 * // get the posts in the current page
 * $posts = $provider->getModels();  // or $provider->models
 * ```
 *
 * For more details and usage information on ActiveDataProvider, see the [guide article on data providers](guide:output-data-providers).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class ActiveDataProvider extends BaseDataProvider
{
    /**
     * @var QueryInterface the query that is used to fetch data models and [[totalCount]]
     * if it is not explicitly set.
     */
    public $query;
    /**
     * @var string|callable the column that is used as the key of the data models.
     * This can be either a column name, or a callable that returns the key value of a given data model.
     *
     * If this is not set, the following rules will be used to determine the keys of the data models:
     *
     * - If [[query]] is an [[\Yiisoft\ActiveRecord\ActiveQuery]] instance, the primary keys of [[\Yiisoft\ActiveRecord\ActiveQuery::modelClass]] will be used.
     * - Otherwise, the keys of the [[models]] array will be used.
     *
     * @see getKeys()
     */
    public $key;
    /**
     * @var Connection|array|string the DB connection object or the application component ID of the DB connection.
     * If not set, the default DB connection will be used.
     * Starting from version 2.0.2, this can also be a configuration array for creating the object.
     */
    public $db;


    /**
     * Create the ActiveDataProvider object.
     * @param Connection $db database connection (if null, default db connection will be used)
     * @param QueryInterface $query query to be executed
     */
    public function __construct(ConnectionInterface $db, QueryInterface $query)
    {
        $this->db = $db;
        $this->query = $query;
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareModels()
    {
        $query = $this->prepareQuery();
        if ($query->emulateExecution) {
            return [];
        }
        return $query->all($this->db);
    }

    /**
     * Prepares the sql-query that will get the data for current page.
     * @return QueryInterface
     * @throws InvalidConfigException
     */
    public function prepareQuery()
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
        if (($sort = $this->getSort()) !== false) {
            $query->addOrderBy($sort->getOrders());
        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareKeys($models)
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
        } elseif ($this->query instanceof ActiveQueryInterface) {
            /* @var $class \Yiisoft\ActiveRecord\ActiveRecordInterface */
            $class = $this->query->modelClass;
            $pks = $class::primaryKey();
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
     * {@inheritdoc}
     */
    protected function prepareTotalCount()
    {
        if (!$this->query instanceof QueryInterface) {
            throw new InvalidConfigException('The "query" property must be an instance of a class that implements the QueryInterface e.g. Yiisoft\Db\Query or its subclasses.');
        }
        $query = clone $this->query;
        return (int) $query->limit(-1)->offset(-1)->orderBy([])->count('*', $this->db);
    }

    /**
     * {@inheritdoc}
     */
    public function setSort($value)
    {
        parent::setSort($value);
        if (($sort = $this->getSort()) !== false && $this->query instanceof ActiveQueryInterface) {
            /* @var $modelClass Model */
            $modelClass = $this->query->modelClass;
            $model = $modelClass::instance();
            if (empty($sort->attributes)) {
                foreach ($model->attributes() as $attribute) {
                    $sort->attributes[$attribute] = [
                        'asc' => [$attribute => SORT_ASC],
                        'desc' => [$attribute => SORT_DESC],
                        'label' => $model->getAttributeLabel($attribute),
                    ];
                }
            } else {
                foreach ($sort->attributes as $attribute => $config) {
                    if (!isset($config['label'])) {
                        $sort->attributes[$attribute]['label'] = $model->getAttributeLabel($attribute);
                    }
                }
            }
        }
    }
}
