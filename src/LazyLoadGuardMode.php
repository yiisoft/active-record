<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

/**
 * Modes of the {@see LazyLoadGuard}.
 */
enum LazyLoadGuardMode
{
    /** A reported lazy load is written as a PSR-3 warning. */
    case Log;
    /** A reported lazy load throws a {@see LazyLoadException}. */
    case Strict;
}
