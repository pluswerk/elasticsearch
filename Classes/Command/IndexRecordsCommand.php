<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Command;

use Exception;
use Pluswerk\Elasticsearch\Utility\HelperUtility;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class IndexRecordsCommand extends AbstractCommand implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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
                try {
                    $helperUtility->indexRecordsByConfiguration($configuration);
                } catch (Exception $exception) {
                    $this->logger->critical($exception->getMessage());
                }
            }
        }

        return 0;
    }
}
