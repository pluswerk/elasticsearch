<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Config;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Pluswerk\Elasticsearch\Exception\ClientNotAvailableException;
use Pluswerk\Elasticsearch\Exception\InvalidConfigurationException;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Object\Container\Container;

class ElasticConfig
{
    protected Site $site;
    protected Client $client;
    protected SiteLanguage $siteLanguage;

    private function __construct()
    {
    }

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     */
    public static function byRequest(ServerRequestInterface $request): ElasticConfig
    {
        $self = new static();

        $self->site = $request->getAttributes()['site'];
        $self->siteLanguage = $request->getAttributes()['language'];
        $self->buildClient();
        return $self;
    }

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     */
    protected function buildClient(): void
    {
        $client = ClientBuilder::create()->setHosts($this->getServerConfig())->build();
        if (null === $client) {
            throw new ClientNotAvailableException('Could not create ElasticConfig');
        }
        $this->client = $client;
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
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
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

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     */
    public static function bySiteAndLanguage(Site $site, SiteLanguage $siteLanguage): ElasticConfig
    {
        $self = new static();
        $self->site = $site;
        $self->siteLanguage = $siteLanguage;
        $self->buildClient();
        return $self;
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

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function getFieldMappingForTable(string $tableName): array
    {
        $index = $this->getIndexName();
        return $this->site->getConfiguration()['elasticsearch']['indices'][$index]['tables'][$tableName]['mapping'] ?? [];
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

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function getConfigForTable(string $tableName): array
    {
        $index = $this->getIndexName();
        return $this->site->getConfiguration()['elasticsearch']['indices'][$index]['tables'][$tableName]['config'] ?? [];
    }

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function getAnalyzers(): array
    {
        $index = $this->getIndexName();
        return $this->site->getConfiguration()['elasticsearch']['indices'][$index]['analyzers'] ?? [];
    }

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function getFilters(): array
    {
        $index = $this->getIndexName();
        return $this->site->getConfiguration()['elasticsearch']['indices'][$index]['filters'] ?? [];
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function isMiddlewareProcessingAllowed(): bool
    {
        $index = $this->getIndexName();
        return !(isset($this->site->getConfiguration()['elasticsearch']['indices'][$index]['usePageMiddleware']) &&
            (bool)$this->site->getConfiguration()['elasticsearch']['indices'][$index]['usePageMiddleware'] === false);
    }

    /**
     * @return array<int, string>
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function getIndexableTables(): array
    {
        $index = $this->getIndexName();
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

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function getIndexingClassForTable(string $tableName): string
    {
        $index = $this->getIndexName();
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

    public function getSiteLanguage(): SiteLanguage
    {
        return $this->siteLanguage;
    }

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function getUriBuilderConfig(string $tableName): array
    {
        $index = $this->getIndexName();
        return $this->site->getConfiguration()['elasticsearch']['indices'][$index]['tables'][$tableName]['uriBuilderConfig'] ?? [];
    }

    public function getPublicHost(): ?string
    {
        $val = getenv('PUBLIC_HOST_ELASTIC_CMS');
        return $val ? (string)$val : null;
    }

    public function getPublicUrl(): string
    {
        $publicHost = $this->getPublicHost();
        if ($publicHost) {
            if (strrpos($publicHost, '/') !== strlen($publicHost) - 1) {
                $publicHost .= '/';
            }
            return $publicHost . $this->getIndexName() . '/_search';
        }

        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(Container::class)->getInstance(UriBuilder::class);

        return $uriBuilder
            ->reset()
            ->setCreateAbsoluteUri(false)
            ->setTargetPageType(1619681085)
            ->build();
    }
}
