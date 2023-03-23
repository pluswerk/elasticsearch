<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\EventListener;

use GuzzleHttp\HandlerStack;
use Pluswerk\Elasticsearch\Middleware\ProgressBodyMiddleware;
use SFC\Staticfilecache\Event\BuildClientEvent;

class BuildClientEventListener
{
    public function __invoke(BuildClientEvent $event): void
    {
        $httpOptions = $event->getHttpOptions();
        $stack = HandlerStack::create();
        $stack->push(function (callable $handler) {
            return new ProgressBodyMiddleware($handler);
        }, 'progress_body');
        $httpOptions['handler'] = $stack;
        $event->setHttpOptions($httpOptions);
    }
}
