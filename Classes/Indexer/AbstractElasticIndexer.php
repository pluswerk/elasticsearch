<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Indexer;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Routing\CommandUriBuilder;
use Pluswerk\Elasticsearch\Service\FrontendSimulationService;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

abstract class AbstractElasticIndexer implements ElasticIndexable
{
    /**
     * @var ElasticConfig
     */
    protected $config;

    /**
     * @var string
     */
    protected $tableName = '';

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var FrontendSimulationService
     */
    protected $frontendSimulationService;

    /**
     * @var OutputInterface
     */
    protected $output;

    public function __construct(ElasticConfig $config, string $tableName, OutputInterface $output)
    {
        $this->output = $output;
        $this->config = $config;
        $this->tableName = $tableName;
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->frontendSimulationService = $this->objectManager->get(FrontendSimulationService::class);
        $this->frontendSimulationService->initTSFE($this->config->getSite()->getRootPageId());
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
        $fieldMapping = $this->config->getFieldMappingForTable($this->tableName);

        foreach ($fieldMapping as $elasticField => $typoField) {
            $body[$elasticField] = $result[$typoField] ?? '';
        }

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
