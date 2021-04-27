<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Exporter;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Domain\Repository\SynonymRepository;
use Pluswerk\Elasticsearch\Exception\ClientNotAvailableException;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

class SynonymExporter
{
    protected SynonymRepository $synonymRepository;
    protected OutputInterface $output;
protected SiteFinder $siteFinder;
    public function __construct(SynonymRepository $synonymRepository, OutputInterface $output, SiteFinder $siteFinder)
    {
        $this->synonymRepository = $synonymRepository;
        $this->output = $output;
        $this->siteFinder=$siteFinder;
    }

    protected array $cache = [];

    /**
     * @param \TYPO3\CMS\Core\Site\Entity\Site $site
     * @param \TYPO3\CMS\Core\Site\Entity\SiteLanguage $siteLanguage
     * @param string $key
     * @return \Pluswerk\Elasticsearch\Config\ElasticConfig
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     */
    protected function config(Site $site, SiteLanguage $siteLanguage, string $key): ElasticConfig
    {
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $config = ElasticConfig::bySiteAndLanguage($site, $siteLanguage);
        // search_analyzer
        //$config->
        // TODO clear the existing synonyms
        // TODO the
        $this->cache[$key] = $config;
        return $config;
    }

    public function all(): void
    {
        foreach ($this->synonymRepository->findAll() as $synonym) {
            try {
                $site = $this->siteFinder->getSiteByPageId($synonym->getPid());
            } catch (SiteNotFoundException $e) {
                $this->output->writeln('Can not export Synonym ' . $synonym . ' because ' . $e->getMessage());
                continue;
            }
            $siteLanguage = $site->getLanguageById($synonym->getLanguageUid());

            try {
                $config = $this->config($site, $siteLanguage, $synonym->getPid() . '-' . $synonym->getLanguageUid());
            } catch (ClientNotAvailableException $e) {
                $this->output->writeln('Can not export Synonym ' . $synonym . ' because ' . $e->getMessage());
                continue;
            }

            // by the synonyms pagetree find the site
            // by the synonyms language find the language
            // that creates configuration - we have core.
            // clear that core
            // index the synonym
            $this->output->writeln($synonym->getUid());
            foreach ($synonym->getTerms() as $term) {
                // TODO synonym needs the word-field
                // "all terms, maybe + word(if self active) => "the word"
                // TODO is the self needed as in solr?
                // TODO aggregate the list for one index completely, then send with the client's body (well first output the debug :)
                /*
                 * PUT /test_index
                    {
                      "settings": {
                        "index": {
                          "analysis": {
                            "filter": {
                              "synonym": {
                                "type": "synonym",
                                "synonyms": [
                                  "i-pod, i pod => ipod",
                                  "universe, cosmos"
                                ]
                              }
                            }
                          }
                        }
                      }
                    }
                 */
                $this->output->writeln($term->getTitle());
            }
        }
    }
}
