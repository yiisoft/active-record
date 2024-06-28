<?php

declare(strict_types=1);

namespace Yiisoft\ActiveRecord;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Db\Connection\ConnectionInterface;

class ConnectionProviderMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ConnectionInterface $db)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        ConnectionProvider::set($this->db);

        return $handler->handle($request);
    }
}
