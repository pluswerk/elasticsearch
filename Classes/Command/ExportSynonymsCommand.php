<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Command;

use Pluswerk\Elasticsearch\Exporter\SynonymExporter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Container\Container;

class ExportSynonymsCommand extends AbstractCommand
{
    protected function configure(): void
    {
        $this->setDescription('Overwrites elastics synonyms with the configuration from database records (synonyms+terms).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        GeneralUtility::makeInstance(Container::class)->getInstance(SynonymExporter::class)->all();
        return 0;
    }
}
