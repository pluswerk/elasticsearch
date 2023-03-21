<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Command;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class CreateIndexCommand extends AbstractCommand
{
    /**
     * @var OutputInterface
     */
    protected OutputInterface $output;

    protected function configure(): void
    {
        $this->setDescription('Manages mapping and indices.');
        $this->addOption('update', 'u', InputOption::VALUE_OPTIONAL, 'Update the mapping instead of creating new indices (only for compatible conversions)', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $this->output = $output;
        $updateMapping = $input->hasOption('update') ? filter_var($input->getOption('update') ?? true, FILTER_VALIDATE_BOOLEAN) : false;
        /** @var Site $site */
        foreach ($siteFinder->getAllSites(false) as $site) {
            $this->createIndexForSite($site, $updateMapping);
        }

        return Command::SUCCESS;
    }

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException|\Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    protected function createIndexForSite(Site $site, bool $updateMapping): void
    {
        $elasticConfigs = ElasticConfig::bySite($site);
        if (empty($elasticConfigs)) {
            $this->output->writeln('Site ' . $site->getIdentifier() . ' has no elasticsearch configuration');
            return;
        }

        if ($updateMapping) {
            $this->output->writeln(sprintf('<comment>Updating mapping for %s</comment>', $site->getIdentifier()));
        } else {
            $this->output->writeln(sprintf('<comment>Creating new elasticsearch index for %s</comment>', $site->getIdentifier()));
        }

        foreach ($elasticConfigs as $config) {
            $client = $config->getClient();
            try {
                $index = $config->getIndexName();
            } catch (InvalidConfigurationException $e) {
                $this->output->writeln('<warning>' . $e->getMessage() . '</warning>');
                continue;
            }

            $params = [
                'index' => $index,
                'body' => [
                    'settings' => [
                        'max_ngram_diff' => 10,
                        'number_of_shards' => 1,
                        'number_of_replicas' => 1,
                        'analysis' => [
                            'hunspell' => [
                                'dictionary' => 'ignore_case'
                            ],
                            'filter' => $config->getFilters(),
                            'analyzer' => $config->getAnalyzers(),
                        ],
                    ],
                    'mappings' => [
                        'properties' => $config->getFieldMapping(),
                    ],
                ],
            ];

            try {
                if ($updateMapping) {
                    $client->indices()->putMapping([
                        'index' => $index,
                        'body' => [
                            'properties' => $config->getFieldMapping(),
                        ]
                    ]);
                    $this->output->writeln(sprintf('<info>Updated mapping "%s" for %s.</info>', $index, $site->getIdentifier()));
                } else {
                    $client->indices()->delete(['index' => $index]);
                    $client->indices()->create($params);
                    $this->output->writeln(sprintf('<info>A new index "%s" has been created for %s.</info>', $index, $site->getIdentifier()));
                }
            } catch (Missing404Exception $e) {
                $this->output->writeln(sprintf('<comment>No index "%s" exists yet, creating new.</comment>', $index));
            }
        }
    }
}
