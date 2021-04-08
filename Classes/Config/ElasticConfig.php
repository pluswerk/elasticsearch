<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Config;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Pluswerk\Elasticsearch\Exception\InvalidConfigurationException;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class ElasticConfig
{
    protected Site $site;
    protected Client $client;
    protected SiteLanguage $siteLanguage;

    private function __construct()
    {
    }

    public static function byRequest(Request $request): ElasticConfig
    {
        $self = new static();
        $self->site = $request->getAttributes()['site'];
        $self->siteLanguage = $request->getAttributes()['language'];
        $self->buildClient();
        return $self;
    }

    private function buildClient(): void
    {
        $this->client = ClientBuilder::create()->setHosts($this->getServerConfig())->build();
    }

    /**
     * @return array<int, string>
     */
    public function getServerConfig(): array
    {
        return array_flip(array_flip($this->site->getConfiguration()['elasticsearch']['server'] ?? []));
    }

    /**
     * @param \TYPO3\CMS\Core\Site\Entity\Site $site
     * @return array<\Pluswerk\Elasticsearch\Config\ElasticConfig>
     */
    public static function bySite(Site $site): array
    {
        $siteLanguages = $site->getLanguages();
        $elasticConfigurations = [];
        foreach ($siteLanguages as $siteLanguage) {
            $elasticConfiguration = new static();
            $elasticConfiguration->site = $site;
            $elasticConfiguration->siteLanguage = $siteLanguage;
            $elasticConfiguration->buildClient();
            $elasticConfigurations[] = $elasticConfiguration;
        }
        return $elasticConfigurations;
    }

    public static function bySiteAndLanguage(Site $site, SiteLanguage $siteLanguage): ElasticConfig
    {
        $self = new static();
        $self->site = $site;
        $self->siteLanguage = $siteLanguage;
        $self->buildClient();
        return $self;
    }

    public function getIndexNames(): array
    {
        return array_column($this->site->getConfiguration()['elasticsearch']['indices'] ?? [], 'index');
    }

    /**
     * @return string
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function getIndexName(): string
    {
        $index = $this->siteLanguage->toArray()['elasticsearch']['index'] ?? '';
        if (!$index) {
            throw new InvalidConfigurationException(
                'The site ' . $this->site->getIdentifier() . ' with language ' . $this->siteLanguage->getTitle() . ' has no index defined'
            );
        }
        if (!isset($this->site->getConfiguration()['elasticsearch']['indices'][$index])) {
            throw new InvalidConfigurationException('The sites index ' . $index . ' has no configuration counterpart in elasticsearch configuration');
        }
        return $index;
    }

    public function getFieldMapping(): array
    {
        if (isset($this->site->getConfiguration()['elasticsearch']['mapping'])) {
            $customMapping = [];

            foreach ($this->site->getConfiguration()['elasticsearch']['mapping'] as $field) {
                $customMapping[$field['name']] = [];
                foreach ($field['parameters'] as $key => $value) {
                    $customMapping[$field['name']][$key] = $value;
                }
            }

            return $customMapping;
        }
        return [];
    }

    public function getFieldMappingForTable(string $index, string $tableName): array
    {
        return $this->site->getConfiguration()['elasticsearch']['indices'][$index]['tables'][$tableName]['mapping'] ?? [];
    }

    public function getConfigForTable(string $index, string $tableName): array
    {
        return $this->site->getConfiguration()['elasticsearch']['indices'][$index]['tables'][$tableName]['config'] ?? [];
    }

    public function getAnalyzers(string $index): array
    {
        return $this->site->getConfiguration()['elasticsearch']['indices'][$index]['analyzers'] ?? [];
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function isMiddlewareProcessingAllowed(string $index): bool
    {
        return !(isset($this->site->getConfiguration()['elasticsearch']['indices'][$index]['usePageMiddleware']) &&
            (bool)$this->site->getConfiguration()['elasticsearch']['indices'][$index]['usePageMiddleware'] === false);
    }

    /**
     * @param string $index
     * @return array<int, string>
     */
    public function getIndexableTables(string $index): array
    {
        if (empty($this->site->getConfiguration()['elasticsearch']['indices'][$index]['tables'])) {
            return [];
        }

        $indexableTables = [];
        foreach ($this->site->getConfiguration()['elasticsearch']['indices'][$index]['tables'] as $tableName => $tableConfiguration) {
            if ($tableConfiguration) {
                $indexableTables[] = $tableName;
            }
        }

        return $indexableTables;
    }

    public function getIndexingClassForTable(string $index, string $tableName): string
    {
        return $this->site->getConfiguration()['elasticsearch']['indices'][$index]['tables'][$tableName]['indexClass'] ?? '';
    }

    public function getSearchFields(): array
    {
        return $this->site->getConfiguration()['elasticsearch']['searchFields'] ?? [];
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getUriBuilderConfig(string $index, string $tableName): array
    {
        return $this->site->getConfiguration()['elasticsearch']['indices'][$index]['tables'][$tableName]['uriBuilderConfig'] ?? [];
    }
}
