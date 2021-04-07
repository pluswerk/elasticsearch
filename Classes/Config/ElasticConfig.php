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

    /**
     * @return array<int, string>
     */
    public function getServerConfig(): array
    {
        return array_flip(array_flip($this->site->getConfiguration()['elasticsearch']['server'] ?? []));
    }

    public function getIndexNames(): array
    {
        return array_column($this->site->getConfiguration()['elasticsearch']['indices'] ?? [], 'index');
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
