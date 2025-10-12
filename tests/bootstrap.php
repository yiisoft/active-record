<?php

declare(strict_types=1);

if (getenv('ENVIRONMENT', local_only: true) === 'local') {
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
    $dotenv->load();
}
