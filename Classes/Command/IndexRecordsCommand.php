<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Command;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Exception\ClientNotAvailableException;
use Pluswerk\Elasticsearch\Exception\InvalidConfigurationException;
use Pluswerk\Elasticsearch\Exception\InvalidIndexerException;
use Pluswerk\Elasticsearch\Indexer\AbstractElasticIndexer;
use Pluswerk\Elasticsearch\Utility\HelperUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
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

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidIndexerException
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        // TODO the output: helperutility can not autowire outputinterface because of abstract in php-fpm
        // TODO and here it should be the cli output...
        $helperUtility = new HelperUtility(new NullOutput());

        $configurations = $helperUtility->getAllConfigurations();
        foreach ($configurations as $configuration) {
            $helperUtility->indexRecordsByConfiguration($configuration);
        }

        return 0;
    }
}
