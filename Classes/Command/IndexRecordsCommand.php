<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Command;

use Pluswerk\Elasticsearch\Utility\HelperUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class IndexRecordsCommand extends AbstractCommand
{
    protected OutputInterface $output;

    protected function configure(): void
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
        parent::execute($input, $output);
        $this->output = $output;

        $helperUtility = GeneralUtility::makeInstance(HelperUtility::class);

        $configurationsByIndex = $helperUtility->getAllConfigurations();
        foreach ($configurationsByIndex as $configurations) {
            foreach ($configurations as $configuration) {
                $helperUtility->indexRecordsByConfiguration($configuration);
            }
        }

        return 0;
    }
}
