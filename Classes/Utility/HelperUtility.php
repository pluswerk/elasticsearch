<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Utility;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
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

class HelperUtility implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @return array<ElasticConfig>
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     */
    public function getAllConfigurations(): array
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $configs = [];
        /** @var Site $site */
        foreach ($siteFinder->getAllSites(false) as $site) {
            if (isset($site->getConfiguration()['elasticsearch'])) {
                $configsBySite = $this->getElasticSiteConfig($site);

                /** @var ElasticConfig $elasticConfig */
                foreach ($configsBySite as $elasticConfig) {
                    if (!isset($configs[$elasticConfig->getIndexName()])) {
                        $configs[$elasticConfig->getIndexName()] = [];
                    }

                    try {
                        $configs[$elasticConfig->getIndexName()][] = $elasticConfig;
                    } catch (InvalidConfigurationException $e) {
                        $this->logger->notice('<warning>' . $e->getMessage() . '</warning>');
                    }
                }
            }
        }
        return $configs;
    }

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     * @return ElasticConfig[]
     */
    protected function getElasticSiteConfig(Site $site): array {
        return ElasticConfig::bySite($site);
    }

    /**
     * @param \Pluswerk\Elasticsearch\Config\ElasticConfig $config
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidIndexerException|\Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function indexRecordsByConfiguration(ElasticConfig $config): void
    {
        //$this->purgeOldAndRestrictedRecords($config);
        foreach ($config->getIndexableTables() as $tableName) {
            $this->indexRecordsByConfigurationAndTableName($config, $tableName);
        }
    }

    /**
     * @param \Pluswerk\Elasticsearch\Config\ElasticConfig $config
     * @param string $type
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function purge(ElasticConfig $config, string $type): void
    {
        $this->logger->notice('<info>Purging old elasticsearch data...</info>');
        $this->bulkDeletePages($config);

        $client = $config->getClient();

        $client->deleteByQuery(
            [
                'index' => $config->getIndexName(),
                'body' => [
                    'query' => [
                        'match' => [
                            'type' => $type
                        ],
                    ],
                ],
            ]
        );
        $this->logger->notice('<info>Finished purging old elasticsearch data.</info>');
    }

    /**
     * @param \Pluswerk\Elasticsearch\Config\ElasticConfig $config
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     * @deprecated
     * @todo revive with a clean command which checks if the elastic index is just up to date. eighter introduce version field and after indexing remove the previous versions for some tables, or like this, check the entries if they are still ok (pages are not indexed all together)
     */
    protected function bulkDeletePages(ElasticConfig $config): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        $conditions[] = $queryBuilder->expr()->eq('deleted', 1);
        $conditions[] = $queryBuilder->expr()->eq('hidden', 1);
        $conditions[] = $queryBuilder->expr()->eq('no_index', 1);
        $conditions[] = $queryBuilder->expr()->eq('no_follow', 1);
        $conditions[] = $queryBuilder->expr()->neq('fe_group', '""');

        $pageResult = $queryBuilder->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->orX()->addMultiple($conditions)
            )
            ->execute()
            ->fetchAllAssociative();

        $client = $config->getClient();

        $iterator = 0;
        foreach ($pageResult as $page) {
            $iterator++;
            $params['body'][] = [
                'delete' => [
                    '_index' => $config->getIndexName(),
                    '_id' => 'pages:' . $page['uid'],
                ],
            ];

            if ($iterator % 1000 === 0) {
                $client->bulk($params);

                // erase the old bulk request
                $params = ['body' => []];
            }
        }

        if (!empty($params['body'])) {
            $client->bulk($params);
        }
    }

    /**
     * @param \Pluswerk\Elasticsearch\Config\ElasticConfig $config
     * @param string $tableName
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidIndexerException
     */
    public function indexRecordsByConfigurationAndTableName(ElasticConfig $config, string $tableName): void
    {
        $indexingClass = $config->getIndexingClassForTable($tableName);
        if ($indexingClass !== '') {
            $indexer = GeneralUtility::makeInstance($indexingClass, $config, $tableName);
            if (!($indexer instanceof AbstractIndexer)) {
                throw new InvalidIndexerException('The indexer has to be an instance of "' . AbstractIndexer::class . '".');
            }

            $indexer->process();
            $this->logger->notice(sprintf('<info>Finished indexing entities of table %s.</info>', $tableName));
        }
    }
}
