<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Indexer;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Exception\TransportException;
use Pluswerk\Elasticsearch\Routing\CommandUriBuilder;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

abstract class AbstractIndexer implements ElasticIndexable
{
    protected ElasticConfig $config;
    protected string $tableName;
    protected string $index;
    protected ObjectManager $objectManager;
    protected OutputInterface $output;

    public function __construct(ElasticConfig $config, string $tableName, string $index, OutputInterface $output)
    {
        $this->output = $output;
        $this->config = $config;
        $this->tableName = $tableName;
        $this->index = $index;
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
    }

    /**
     * @return mixed
     * @throws \Pluswerk\Elasticsearch\Exception\TransportException
     */
    protected function getContent()
    {
        $uri = $this->config->getConfigForTable($this->index, $this->tableName)['uri'] ?? '';
        if (!parse_url($uri)) {
            throw new TransportException('Uri not valid ' . $uri);
        }

        $content = GeneralUtility::getUrl($uri);
        if (!$content) {
            throw new TransportException('Could not GET uri ' . $uri);
        }
        return $content;
    }

    protected function getIndexBody(string $id): array
    {
        return [
            'index' => [
                '_index' => $this->index,
                '_id' => $id,
            ],
        ];
    }
    protected function extractId(array &$result): string
    {
        $id = $this->tableName . ':' . $result['uid'];
        unset($result['uid']);
        return $id;
    }

    protected function findAllTableEntries()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($this->tableName);
        return $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->execute()
            ->fetchAll();
    }

    protected function getDocumentBody(array $result): array
    {
        $body = [];
        $fieldMapping = $this->config->getFieldMappingForTable($this->index, $this->tableName);

        foreach ($fieldMapping as $elasticField => $typoField) {
            if (isset($result[$typoField])) {
                $body[$elasticField] = $result[$typoField];
            } else {
                $this->output->writeln('<warning>Could not find field '.$typoField.' to map</warning>');
            }
        }
        if (isset($body['uid'])) {
            unset($body['uid']);
        }
        $type = $this->tableName;
        if (isset($body['type'])) {
            $type .= '/' . $body['type'];
        }
        $body['type'] = $type;
        return $body;
    }

    protected function buildUrlFor(
        array $uriBuilderConfig
    ): string {
        $pageUid = $uriBuilderConfig['pageUid'] ?? 0;
        $extensionName = $uriBuilderConfig['extensionName'] ?? '';
        $pluginName = $uriBuilderConfig['pluginName'] ?? '';
        $controllerName = $uriBuilderConfig['controllerName'] ?? '';
        $actionName = $uriBuilderConfig['actionName'] ?? '';
        $argumentName = $uriBuilderConfig['argumentName'] ?? '';
        $entityUid = $uriBuilderConfig['uid'] ?? '';
        $uriBuilder = $this->objectManager->get(CommandUriBuilder::class);

        return $uriBuilder
            ->reset()
            ->setTargetPageUid($pageUid)
            ->setCreateAbsoluteUri(true)
            ->uriFor(
                $actionName,
                [
                    $argumentName => $entityUid,
                ],
                $controllerName,
                $extensionName,
                $pluginName
            );
    }
}
