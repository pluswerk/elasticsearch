<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Command;

use Pluswerk\Elasticsearch\Utility\HelperUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class IndexRecordsCommand extends Command
{
    protected OutputInterface $output;

    public function configure(): void
    {
        $this->setDescription('Indexes records based on your yaml file.');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidIndexerException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $helperUtility = GeneralUtility::makeInstance(HelperUtility::class);

        $configurations = $helperUtility->getAllConfigurations();
        foreach ($configurations as $configuration) {
            $helperUtility->indexRecordsByConfiguration($configuration);
        }

        return 0;
    }
}
