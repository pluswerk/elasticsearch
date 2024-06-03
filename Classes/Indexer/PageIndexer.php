<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Indexer;

use Exception;
use Psr\Http\Message\UriInterface;
use Psr\Log\LogLevel;

class PageIndexer extends AbstractIndexer
{
    public function getIndexableContent(string $html): string
    {
        $indexableContents = [];
        preg_match_all('/<!--\s*?TYPO3SEARCH_begin\s*?-->.*?<!--\s*?TYPO3SEARCH_end\s*?-->/mis', $html, $indexableContents);
        $indexableContents = implode('', $indexableContents[0]);
        return trim(preg_replace('/<!--(.*)-->/Uis', '', $indexableContents));
    }

    public function generateIdByDocument(string $identifier): string
    {
        return 'pages:' . $identifier;
    }

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function removeContentById(string $id): void
    {
        $this->config->getClient()->delete([
            'id' => $id,
            'index' => $this->config->getIndexName()
        ]);
    }

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     */
    public function indexContent(array $page, UriInterface $url): void
    {
        $page['url'] = $url->getPath();
        $pagesMapping = $this->config->getFieldMappingForTable('pages');
        if (!isset($page['type'])) {
            $page['type'] = 'pages';
        }
        if (!isset($pagesMapping['type'])) {
            $pagesMapping['type'] = 'type';
        }
        $indexBody = [];
        foreach ($pagesMapping as $elasticField => $typoField) {
            $indexBody[$elasticField] = $page[$typoField] ?? null;
        }

        $indexBody['id'] = $this->generateIdByDocument((string)$indexBody['id']);

        $indexingParameters = [
            'index' => $this->config->getIndexName(),
            'id' => 'pages:' . $page['uid'],
            'body' => $indexBody
        ];

        try {
            $client = $this->config->getClient();
            $client->index($indexingParameters);
        } catch (Exception $e) {
            $this->logger->log(LogLevel::WARNING, $e->getMessage());
        }
    }
}
