<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Exporter;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Domain\Model\Synonym;
use Pluswerk\Elasticsearch\Domain\Repository\SynonymRepository;
use Pluswerk\Elasticsearch\Exception\ClientNotAvailableException;
use Pluswerk\Elasticsearch\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\Session;

class SynonymExporter
{
    protected SynonymRepository $synonymRepository;
    protected OutputInterface $output;
    protected SiteFinder $siteFinder;
    protected array $cache = [];

    public function __construct(SynonymRepository $synonymRepository, OutputInterface $output, SiteFinder $siteFinder)
    {
        $this->synonymRepository = $synonymRepository;
        $this->output = $output;
        $this->siteFinder = $siteFinder;
        /** @var Session $session */
        $session = GeneralUtility::makeInstance(ObjectManager::class)->get(Session::class);
        $session->destroy();
    }

    public function all(): void
    {
        foreach ($this->synonymRepository->findAll() as $synonym) {
            $this->one($synonym);
        }
        $this->send();
    }

    /**
     * Sending the synonym destroys all other, so always ALL synonyms have to be taken into account (yet)
     *
     * @param \Pluswerk\Elasticsearch\Domain\Model\Synonym $synonym
     */
    protected function one(Synonym $synonym): void
    {
        $filters = $synonym->getFilters();
        if (!$filters) {
            $this->output->writeln('Ignoring synonym without filter: ' . $synonym);
            return;
        }

        $terms = $synonym->getTerms();
        if (!$terms->count()) {
            $this->output->writeln('Ignoring synonym without terms: ' . $synonym);
            return;
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($synonym->getPid());
        } catch (SiteNotFoundException $e) {
            $this->output->writeln('Can not export Synonym ' . $synonym . ' because ' . $e->getMessage());
            return;
        }
        $siteLanguage = $site->getLanguageById($synonym->getLanguageUid());

        $key = $synonym->getPid() . '-' . $synonym->getLanguageUid();
        try {
            $this->config($site, $siteLanguage, $key);
        } catch (ClientNotAvailableException $e) {
            $this->output->writeln('Can not export Synonym ' . $synonym . ' because ' . $e->getMessage());
            return;
        }

        $termString = '';
        foreach ($terms as $term) {
            $termString .= ($termString ? ', ' : '') . $term->getTitle();
        }

        if ($synonym->isSelf()) {
            $synonymString = $termString . ' => ' . $synonym->getTitle();
        } else {
            $termString .= ($termString ? ', ' : '') . $synonym->getTitle();
            $synonymString = $termString . ' => ' . $termString;
        }

        foreach ($filters as $filter) {
            $this->cache[$key]['synonyms'][$filter][] = $synonymString;
            $this->output->writeln('Adding Synonym [' . $synonymString . '] to filter [' . $filter . ']');
        }
    }

    /**
     * @param \TYPO3\CMS\Core\Site\Entity\Site $site
     * @param \TYPO3\CMS\Core\Site\Entity\SiteLanguage $siteLanguage
     * @param string $key
     * @return \Pluswerk\Elasticsearch\Config\ElasticConfig
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     */
    protected function config(Site $site, SiteLanguage $siteLanguage, string $key): ElasticConfig
    {
        if (isset($this->cache[$key]['config'])) {
            return $this->cache[$key]['config'];
        }

        $config = ElasticConfig::bySiteAndLanguage($site, $siteLanguage);
        $this->cache[$key]['config'] = $config;
        return $config;
    }

    protected function send(): void
    {
        foreach ($this->cache as $configAndSynonyms) {
            /** @var ElasticConfig $config */
            $config = $configAndSynonyms['config'];

            try {
                $ctrl = ['index' => $config->getIndexName(),];
                $filters = [];
                foreach ($configAndSynonyms['synonyms'] as $filter => $list) {
                    $filters[$filter] = [
                    'type' => 'synonym',
                    'synonyms' => $list,
                    ];
                }
                $params = [
                    'index' => $config->getIndexName(),
                    'body' => [
                        'settings' => [
                            'index' => [
                                'analysis' => [
                                    'filter' => $filters
                                ],
                            ],
                        ],
                    ],
                ];
            } catch (InvalidConfigurationException $e) {
                $this->output->writeln($e->getMessage());
                continue;
            }

            $client = $config->getClient();
            $client->indices()->close($ctrl);
            $x = $client->indices()->putSettings($params);
            $client->indices()->open($ctrl);
            if (!($x['acknowledged'] ?? false)) {
                $this->output->writeln('Acknowledged was false sending data to index ' . print_r($ctrl, true));
            }
        }
    }
}
