<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Command;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Exception\ClientNotAvailableException;
use Pluswerk\Elasticsearch\Exception\InvalidIndexerException;
use Pluswerk\Elasticsearch\Indexer\AbstractElasticIndexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class IndexRecordsCommand extends Command
{
    /**
     * @var OutputInterface
     */
    private $output;

    public function configure(): void
    {
        $this->setDescription('Indexes records based on your yaml file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $this->output = $output;
        /** @var Site $site */
        foreach ($siteFinder->getAllSites(false) as $site) {
            if (isset($site->getConfiguration()['elasticsearch'])) {
                $config = GeneralUtility::makeInstance(ElasticConfig::class, $site);
                $this->purgeOldAndRestrictedRecords($config);
                $this->indexRecordsForSite($config);
            }
        }
    }

    private function debug($var, int $depth = 4): void
    {
        \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($var, '$var (' . __FILE__ . ':' . __LINE__ . ')', $depth, true);
    }

    private function indexRecordsForSite(ElasticConfig $config): void
    {
        foreach ($config->getIndexableTables() as $tableName) {
            $indexingClass = $config->getIndexingClassForTable($tableName);
            if ($indexingClass !== '') {
                $indexer = GeneralUtility::makeInstance($indexingClass, $config, $tableName, $this->output);

                if (!($indexer instanceof AbstractElasticIndexer)) {
                    throw new InvalidIndexerException('The indexer has to be an instance of "' . AbstractElasticIndexer::class . '".');
                }

                $indexer->process();
                $this->output->writeln(sprintf('<info>Finished indexing entities of table %s.</info>', $tableName));
            }
        }
    }

    private function purgeOldAndRestrictedRecords(ElasticConfig $config): void
    {
        $this->output->writeln('<info>Purging old elasticsearch data...</info>');
        $this->bulkDeletePages($config);

        $config->getClient()->deleteByQuery(
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

    private function bulkDeletePages(ElasticConfig $config): void
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
                $responses = $client->bulk($params);

                // erase the old bulk request
                $params = ['body' => []];

                // unset the bulk response when you are done to save memory
                unset($responses);
            }
        }

        if (!empty($params['body'])) {
            $responses = $client->bulk($params);
        }
    }
}
