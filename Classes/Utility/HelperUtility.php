<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Utility;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Exception\ClientNotAvailableException;
use Pluswerk\Elasticsearch\Exception\InvalidIndexerException;
use Pluswerk\Elasticsearch\Indexer\AbstractElasticIndexer;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class HelperUtility
{
    protected OutputInterface $output;
    public function __construct(NullOutput $output)
    {
        $this->output = $output;
    }

    /**
     * @return array<ElasticConfig>
     */
    public function getAllConfigurations(): array
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $configs = [];
        /** @var Site $site */
        foreach ($siteFinder->getAllSites(false) as $site) {
            if (isset($site->getConfiguration()['elasticsearch'])) {
                $configs[] = GeneralUtility::makeInstance(ElasticConfig::class, $site);
            }
        }
        return $configs;
    }

    /**
     * @param \Pluswerk\Elasticsearch\Config\ElasticConfig $config
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidIndexerException|\Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     */
    public function indexRecordsByConfiguration(ElasticConfig $config): void
    {
        $this->purgeOldAndRestrictedRecords($config);
        $indices = $config->getIndexNames();
        foreach ($indices as $index) {
            foreach ($config->getIndexableTables($index) as $tableName) {
                $this->indexRecordsByConfigurationAndTableName($config, $index, $tableName);
            }
        }
    }

    /**
     * @param \Pluswerk\Elasticsearch\Config\ElasticConfig $config
     * @param string $index
     * @param string $tableName
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidIndexerException
     */
    public function indexRecordsByConfigurationAndTableName(ElasticConfig $config, string $index, string $tableName): void
    {
        $indexingClass = $config->getIndexingClassForTable($index, $tableName);
        if ($indexingClass !== '') {
            $indexer = GeneralUtility::makeInstance($indexingClass, $config, $tableName, $index, $this->output);
            if (!($indexer instanceof AbstractElasticIndexer)) {
                throw new InvalidIndexerException('The indexer has to be an instance of "' . AbstractElasticIndexer::class . '".');
            }

            $indexer->process();
            $this->output->writeln(sprintf('<info>Finished indexing entities of table %s.</info>', $tableName));
        }
    }

    /**
     * @param \Pluswerk\Elasticsearch\Config\ElasticConfig $config
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     */
    protected function purgeOldAndRestrictedRecords(ElasticConfig $config): void
    {
        $this->output->writeln('<info>Purging old elasticsearch data...</info>');
        $this->bulkDeletePages($config);

        $client = $config->getClient();
        if ($client === null) {
            throw new ClientNotAvailableException('No elasticsearch client was found.');
        }

        $client->deleteByQuery(
            [
                'index' => $config->getIndexName(),
                'body' => [
                    'query' => [
                        'bool' => [
                            'must_not' => [
                                'term' => [
                                    'id' => 'pages',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );
        $this->output->writeln('<info>Finished purging old elasticsearch data.</info>');
    }

    /**
     * @param \Pluswerk\Elasticsearch\Config\ElasticConfig $config
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
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
            ->fetchAll();

        $client = $config->getClient();

        if ($client === null) {
            throw new ClientNotAvailableException('No elasticsearch client was found.');
        }

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
}
