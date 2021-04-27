<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Command;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Exception\InvalidConfigurationException;
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
    protected $output;

    public function configure(): void
    {
        $this->setDescription('Deletes old index and creates a new one.');
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $this->output = $output;

        /** @var Site $site */
        foreach ($siteFinder->getAllSites(false) as $site) {
            if (isset($site->getConfiguration()['elasticsearch'])) {
                $this->createIndexForSite($site);
            } else {
                $output->writeln('Site ' . $site->getIdentifier() . ' has no elasticsearch configuration');
            }
        }

        return 0;
    }

    /**
     * @param \TYPO3\CMS\Core\Site\Entity\Site $site
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException|\Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    protected function createIndexForSite(Site $site): void
    {
        $this->output->writeln(sprintf('<comment>Creating new elasticsearch index for %s</comment>', $site->getIdentifier()));
        $elasticConfigs = ElasticConfig::bySite($site);
        foreach ($elasticConfigs as $config) {
            $client = $config->getClient();

            try {
                $index = $config->getIndexName();
            } catch (InvalidConfigurationException $e) {
                $this->output->writeln('<warning>' . $e->getMessage() . '</warning>');
                continue;
            }
            $this->output->writeln('<comment>Deleting old index..</comment>');
            try {
                $client->indices()->delete(['index' => $index]);
            } catch (Missing404Exception $e) {
                $this->output->writeln(sprintf('<comment>No index "%s" exists yet, creating new now..</comment>', $index));
            }

            $params = [
                'index' => $index,
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

            $client->indices()->create($params);
            $this->output->writeln(sprintf('<info>A new index "%s" has been created for %s.</info>', $index, $site->getIdentifier()));
        }
    }
}
