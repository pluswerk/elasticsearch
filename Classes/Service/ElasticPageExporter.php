<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Service;

use Exception;
use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Exception\ClientNotAvailableException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class ElasticPageExporter extends AbstractElasticPageExporter
{

    public function __construct(RequestInterface $request)
    {
        $this->elasticConfig = ElasticConfig::byRequest($request);
    }

}
