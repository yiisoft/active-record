<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Exception;
use Psr\Log\LoggerInterface;
use WeakMap;
use Yiisoft\ActiveRecord\Trait\LazyLoadGuardTrait;

use function class_uses;
use function get_parent_class;
use function reset;

/**
 * Detects N+1 queries caused by lazy loading of relations in models using {@see LazyLoadGuardTrait}.
 */
final class LazyLoadGuard
{
    private static LazyLoadGuardMode $mode = LazyLoadGuardMode::Log;
    private static ?LoggerInterface $logger = null;

    /**
     * @var int[] Number of registered lazy loads `[model_class::relation_name => count, ...]`
     */
    private static array $counters = [];

    /**
     * @var WeakMap|null The result set each model was populated from, keyed by model.
     */
    private static ?WeakMap $resultSets = null;

    /**
     * @var int[] Number of lazy loads within one result set `[result_set_id model_class::relation_name => count]`
     */
    private static array $resultSetCounters = [];

    private static int $lastResultSetId = 0;

    /**
     * @var bool[] Whether a model class uses {@see LazyLoadGuardTrait} `[model_class => bool, ...]`
     */
    private static array $guardedClasses = [];

    /**
     * Sets the mode of the guard and the logger to report to. The logger is only used in the
     * {@see LazyLoadGuardMode::Log} mode, since {@see LazyLoadGuardMode::Strict} carries the same information in
     * the exception it throws.
     */
    public static function set(LazyLoadGuardMode $mode, ?LoggerInterface $logger = null): void
    {
        self::$mode = $mode;
        self::$logger = $logger;
    }

    /**
     * Groups the models of one result set, so that the guard can tell a relation loaded for several records of
     * the same result set, which is an N+1 query, from the same relation loaded for records that were each
     * fetched by a query of their own, which has nothing to eager load across.
     *
     * @param ActiveRecordInterface[]|array[] $models The models populated from one result set.
     *
     * @see ActiveQuery::populate()
     */
    public static function registerResultSet(array $models): void
    {
        $firstModel = reset($models);

        if (!$firstModel instanceof ActiveRecordInterface || !self::isGuarded($firstModel::class)) {
            return;
        }

        self::$resultSets ??= new WeakMap();
        $resultSetId = ++self::$lastResultSetId;

        foreach ($models as $model) {
            if ($model instanceof ActiveRecordInterface) {
                self::$resultSets[$model] = $resultSetId;
            }
        }
    }

    /**
     * Registers a lazy load of the relation and reports it according to the mode, starting from the second record
     * of the same result set that loads the relation, since one record loading a relation isn't an N+1 problem.
     *
     * @throws LazyLoadException In the {@see LazyLoadGuardMode::Strict} mode.
     */
    public static function check(ActiveRecordInterface $model, string $relationName): void
    {
        $modelClass = $model::class;
        $relation = $modelClass . '::' . $relationName;

        self::$counters[$relation] = (self::$counters[$relation] ?? 0) + 1;

        $resultSets = self::$resultSets ??= new WeakMap();

        /** A record populated outside a result set is a result set of its own. */
        $resultSets[$model] ??= ++self::$lastResultSetId;

        /** @var int $resultSetId */
        $resultSetId = $resultSets[$model];

        $counter = $resultSetId . ' ' . $relation;
        $count = self::$resultSetCounters[$counter] = (self::$resultSetCounters[$counter] ?? 0) + 1;

        if ($count === 1) {
            return;
        }

        $message = "Relation \"$relation\" is lazy loaded by $count records of one result set."
            . " Eager load it with \"with('$relationName')\" to run one query instead of $count.";

        if (self::$mode === LazyLoadGuardMode::Strict) {
            throw new LazyLoadException($message, $relationName);
        }

        self::$logger?->warning(
            $message,
            [
                'model' => $modelClass,
                'relation' => $relationName,
                'count' => $count,
                'trace' => (new Exception())->getTraceAsString(),
            ],
        );
    }

    /**
     * Returns the number of registered lazy loads `[model_class::relation_name => count, ...]`, including the ones
     * that weren't reported because they belong to result sets of their own.
     *
     * @return int[]
     */
    public static function getCounters(): array
    {
        return self::$counters;
    }

    /**
     * Resets the mode, the logger and the counters to their defaults.
     */
    public static function reset(): void
    {
        self::$mode = LazyLoadGuardMode::Log;
        self::$logger = null;
        self::$counters = [];
        self::$resultSets = null;
        self::$resultSetCounters = [];
        self::$lastResultSetId = 0;
    }

    /**
     * @param class-string $modelClass
     */
    private static function isGuarded(string $modelClass): bool
    {
        if (isset(self::$guardedClasses[$modelClass])) {
            return self::$guardedClasses[$modelClass];
        }

        $traits = [];

        for ($class = $modelClass; $class !== false; $class = get_parent_class($class)) {
            $traits += class_uses($class) ?: [];
        }

        return self::$guardedClasses[$modelClass] = isset($traits[LazyLoadGuardTrait::class]);
    }
}
