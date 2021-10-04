<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Utility;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Config\RemoteElasticConfig;
use Pluswerk\Elasticsearch\Exception\InvalidConfigurationException;
use Pluswerk\Elasticsearch\Exception\InvalidIndexerException;
use Pluswerk\Elasticsearch\Indexer\AbstractIndexer;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RemoteHelperUtility extends HelperUtility
{
    /**
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     */
    protected function getElasticSiteConfig(Site $site): array {
        return RemoteElasticConfig::bySite($site);
    }
}
