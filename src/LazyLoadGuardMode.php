<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

/**
 * Modes of the {@see LazyLoadGuard}.
 */
enum LazyLoadGuardMode
{
    /** Every lazy load is reported as a PSR-3 warning. */
    case Log;
    /** Every lazy load throws a `LogicException`. */
    case Strict;
}
