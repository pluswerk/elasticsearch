<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Indexer;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Exception\TransportException;
use Pluswerk\Elasticsearch\Routing\CommandUriBuilder;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

abstract class AbstractIndexer implements ElasticIndexable, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected ElasticConfig $config;
    protected string $tableName;
    protected ObjectManager $objectManager;
    protected OutputInterface $output;

    public function __construct(ElasticConfig $config, string $tableName)
    {
        $this->objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->config = $config;
        $this->tableName = $tableName;
    }

    public function process(): void
    {
        $client = $this->config->getClient();

        $results = $this->getContent();

        $params = [];
        $iterator = 0;

        $this->logger->notice(sprintf('<info>Indexing %s entities of %s...</info>', count($results), $this->tableName));

        foreach ($results as $result) {
            $iterator++;
            $id = $this->extractId($result);

            $params['body'][] = $this->getIndexBody($id);
            $documentBody = $this->getDocumentBody($result);

            $params['body'][] = $documentBody;

            // Every 1000 documents stop and send the bulk request
            if ($iterator % 1000 === 0) {
                $client->bulk($params);

                // erase the old bulk request
                $params = ['body' => []];
            }
        }

        if (!empty($params['body'])) {
            $client->bulk($params);
        }
    }

    /**
     * Downloads data from URI
     *
     * @return mixed
     * @throws \Pluswerk\Elasticsearch\Exception\TransportException
     */
    protected function getContent()
    {
        $uri = $this->config->getConfigForTable($this->tableName)['uri'] ?? '';
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
                '_index' => $this->config->getIndexName(),
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



    protected function getDocumentBody(array $result): array
    {
        $body = [];
        $fieldMapping = $this->config->getFieldMappingForTable($this->tableName);

        foreach ($fieldMapping as $elasticField => $typoField) {
            if (isset($result[$typoField])) {
                $body[$elasticField] = $result[$typoField];
            } else {
                $this->logger->notice('<warning>Could not find field '.$typoField.' to map</warning>');
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
