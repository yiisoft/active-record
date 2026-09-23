<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Exception;
use LogicException;
use Psr\Log\LoggerInterface;
use Yiisoft\ActiveRecord\Trait\LazyLoadGuardTrait;

/**
 * Detects N+1 queries caused by lazy loading of relations in models using {@see LazyLoadGuardTrait}.
 */
final class LazyLoadGuard
{
    private static LazyLoadGuardMode $mode = LazyLoadGuardMode::Log;
    private static ?LoggerInterface $logger = null;

    /**
     * @var int[] Number of detected lazy loads `[model_class::relation_name => count, ...]`
     */
    private static array $counters = [];

    /**
     * Sets the mode of the guard and the logger used in the {@see LazyLoadGuardMode::Log} mode.
     */
    public static function set(LazyLoadGuardMode $mode, ?LoggerInterface $logger = null): void
    {
        self::$mode = $mode;
        self::$logger = $logger;
    }

    /**
     * Registers a lazy load of the relation and reports it according to the mode, starting from the second lazy load
     * of the same relation, since loading a relation once isn't an N+1 problem.
     *
     * @throws LogicException In the {@see LazyLoadGuardMode::Strict} mode.
     */
    public static function check(ActiveRecordInterface $model, string $relationName): void
    {
        $modelClass = $model::class;
        $relation = $modelClass . '::' . $relationName;

        self::$counters[$relation] = (self::$counters[$relation] ?? 0) + 1;

        if (self::$counters[$relation] === 1) {
            return;
        }

        if (self::$mode === LazyLoadGuardMode::Strict) {
            throw new LogicException("Relation \"$relation\" is lazy loaded.");
        }

        self::$logger?->warning(
            "Relation \"$relation\" is lazy loaded.",
            [
                'model' => $modelClass,
                'relation' => $relationName,
                'count' => self::$counters[$relation],
                'trace' => (new Exception())->getTraceAsString(),
            ],
        );
    }

    /**
     * Returns the number of detected lazy loads `[model_class::relation_name => count, ...]`.
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
    }
}
