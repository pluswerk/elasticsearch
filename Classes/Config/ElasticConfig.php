<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Config;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use TYPO3\CMS\Core\Site\Entity\Site;

class ElasticConfig
{
    /**
     * @var Site
     */
    private $site;

    /**
     * @var Client
     */
    private $client;
    private $defaultIndex = 'typo3';

    public function __construct(Site $site)
    {
        $this->site = $site;
        $this->client = ClientBuilder::create()
            ->setHosts($this->getServerConfig())
            ->build();
    }

    public function getServerConfig(): array
    {
        if (isset($this->site->getConfiguration()['elasticsearch']['server'])) {
            $serverConfig = [];

            foreach ($this->site->getConfiguration()['elasticsearch']['server'] as $key => $value) {
                $serverConfig[$key] = $value;
            }

            return $serverConfig;
        }

        return [];
    }

    public function getIndexName(): string
    {
        return $this->site->getConfiguration()['elasticsearch']['index'] ?? $this->defaultIndex;
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

    public function getFieldMappingForTable(string $tableName): array
    {
        return $this->site->getConfiguration()['elasticsearch']['tables'][$tableName]['mapping'] ?? [];
    }

    public function getAnalyzers(): array
    {
        return $this->site->getConfiguration()['elasticsearch']['analyzers'] ?? [];
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function isMiddlewareProcessingAllowed(): bool
    {
        if (
            isset($this->site->getConfiguration()['elasticsearch']['usePageMiddleware']) &&
            (bool)$this->site->getConfiguration()['elasticsearch']['usePageMiddleware'] === false
        ) {
            return false;
        }

        return true;
    }

    public function getIndexableTables(): array
    {
        if (isset($this->site->getConfiguration()['elasticsearch']['tables'])) {
            return array_keys($this->site->getConfiguration()['elasticsearch']['tables']);
        }

        return [];
    }

    public function getIndexingClassForTable($tableName): string
    {
        return $this->site->getConfiguration()['elasticsearch']['tables'][$tableName]['indexClass'] ?? '';
    }

    public function getSearchFields(): array
    {
        return $this->site->getConfiguration()['elasticsearch']['searchFields'] ?? [];
    }

    public function getSite(): Site
    {
        return $this->site;
    }

    public function getUriBuilderConfig(string $tableName): array
    {
        return $this->site->getConfiguration()['elasticsearch']['tables'][$tableName]['uriBuilderConfig'] ?? [];
    }
}
