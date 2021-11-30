<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Command;

use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CreateIndexCommand extends Command
{
    /**
     * @var OutputInterface
     */
    private $output;

    public function configure(): void
    {
        $this->setDescription('Deletes old index and creates a new one.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $this->output = $output;
        /** @var Site $site */
        foreach ($siteFinder->getAllSites(false) as $site) {
            if (isset($site->getConfiguration()['elasticsearch'])) {
                $this->createIndexForSite($site);
            }
        }

        return Command::SUCCESS;
    }

    private function createIndexForSite(Site $site): void
    {
        $this->output->writeln(sprintf('<comment>Creating new elasticsearch index for %s</comment>', $site->getIdentifier()));
        $config = GeneralUtility::makeInstance(ElasticConfig::class, $site);

        $this->output->writeln('<comment>Deleting old index..</comment>');

        try {
            $config->getClient()->indices()->delete(['index' => $config->getIndexName()]);
        } catch (Missing404Exception $e) {
            $this->output->writeln(sprintf('<comment>No index "%s" exists yet, creating new now..</comment>', $config->getIndexName()));
        }

        $params = [
            'index' => $config->getIndexName(),
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 1,
                    'analysis' => [
                        'analyzer' => $config->getAnalyzers(),
                    ],
                ],
                'mappings' => [
                    'properties' => $config->getFieldMapping(),
                ],
            ],
        ];

        $config->getClient()->indices()->create($params);
        $this->output->writeln(sprintf('<info>A new index "%s" has been created for %s.</info>', $config->getIndexName(), $site->getIdentifier()));
    }

    private function debug($var, int $depth = 4): void
    {
        \TYPO3\CMS\Extbase\Utility\DebuggerUtility::var_dump($var, '$var (' . __FILE__ . ':' . __LINE__ . ')', $depth, true);
    }
}
