<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Exception;
use Psr\Log\LoggerInterface;
use WeakMap;
use Yiisoft\ActiveRecord\Internal\LazyLoadResultSet;
use Yiisoft\ActiveRecord\Trait\LazyLoadGuardTrait;

use function class_uses;
use function get_parent_class;

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
     *
     * @psalm-var WeakMap<ActiveRecordInterface, LazyLoadResultSet>|null
     */
    private static ?WeakMap $resultSets = null;

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
     * Groups the models of one result set, so that the guard can tell a relation loaded by several records of
     * the same result set, which is an N+1 query, from the same relation loaded by records that were each
     * fetched by a query of their own, which has nothing to eager load across.
     *
     * @param ActiveRecordInterface[]|array[] $models The models populated from one result set.
     *
     * @see ActiveQuery::populate()
     */
    public static function registerResultSet(array $models): void
    {
        $resultSets = null;
        $resultSet = null;

        foreach ($models as $model) {
            if (!$model instanceof ActiveRecordInterface || !self::isGuarded($model::class)) {
                continue;
            }

            $resultSets ??= self::resultSets();
            $resultSet ??= new LazyLoadResultSet();
            $resultSets[$model] = $resultSet;
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

        $resultSets = self::resultSets();
        $resultSet = $resultSets[$model] ?? null;

        if ($resultSet === null) {
            /** A record populated outside a result set is a result set of its own. */
            $resultSet = new LazyLoadResultSet();
            $resultSets[$model] = $resultSet;
        }

        $count = $resultSet->registerLazyLoad($model, $relation);

        if ($count === 1) {
            return;
        }

        $message = "Relation \"$relation\" is lazy loaded by $count records of one result set."
            . " Eager load it with \"with('$relationName')\" to run one query instead of $count.";

        if (self::$mode === LazyLoadGuardMode::Strict) {
            throw new LazyLoadException($message, $modelClass, $relationName);
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
     * Clears the counters and the registered result sets, keeping the mode and the logger. Call it between the
     * requests of a long-running worker, so that the result sets of one request aren't counted against the next.
     */
    public static function resetCounters(): void
    {
        self::$counters = [];
        self::$resultSets = null;
    }

    /**
     * Resets the counters, the mode and the logger to their defaults.
     */
    public static function reset(): void
    {
        self::$mode = LazyLoadGuardMode::Log;
        self::$logger = null;

        self::resetCounters();
    }

    /**
     * @psalm-return WeakMap<ActiveRecordInterface, LazyLoadResultSet>
     */
    private static function resultSets(): WeakMap
    {
        /** @psalm-var WeakMap<ActiveRecordInterface, LazyLoadResultSet> $resultSets */
        $resultSets = self::$resultSets ?? new WeakMap();

        return self::$resultSets = $resultSets;
    }

    /**
     * @param class-string $modelClass
     */
    private static function isGuarded(string $modelClass): bool
    {
        return self::$guardedClasses[$modelClass] ??= self::usesGuardTrait($modelClass);
    }

    /**
     * Whether the class, one of its parents or one of the traits they use, directly or through another trait,
     * uses {@see LazyLoadGuardTrait}. {@see class_uses()} only returns the traits used directly by the class.
     *
     * @param class-string $class
     */
    private static function usesGuardTrait(string $class): bool
    {
        foreach (class_uses($class) ?: [] as $trait) {
            if ($trait === LazyLoadGuardTrait::class || self::usesGuardTrait($trait)) {
                return true;
            }
        }

        $parent = get_parent_class($class);

        return $parent !== false && self::usesGuardTrait($parent);
    }
}
