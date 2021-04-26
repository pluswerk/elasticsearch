<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Service;

use Exception;
use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Exception\ClientNotAvailableException;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

abstract class AbstractElasticPageExporter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var ElasticConfig
     */
    protected $elasticConfig;

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

    public function removeContentById(string $id)
    {
        $this->elasticConfig->getClient()->delete([
            'id'=> $id,
            'index' => $this->elasticConfig->getIndexName()
        ]);
    }

    public function indexContent(array $page, UriInterface $url): void
    {
        $page['url'] = $url->getPath();
        $pagesMapping = $this->elasticConfig->getFieldMappingForTable($this->elasticConfig->getIndexName(), 'pages');
        if (!isset($page['type'])) {
            $page['type'] = 'pages';
        }
        if (!isset($pagesMapping['type'])) {
            $pagesMapping['type'] = 'type';
        }
        foreach ($pagesMapping as $elasticField => $typoField) {
            $indexBody[$elasticField] = $page[$typoField] ?? '';
        }

        $indexBody['id'] = $this->generateIdByDocument($indexBody['id']);

        $indexingParameters = [
            'index' => $this->elasticConfig->getIndexName(),
            'id' => 'pages:' . $page['uid'],
            'body' => $indexBody
        ];

        try {
            $client = $this->elasticConfig->getClient();
            $client->index($indexingParameters);
        } catch (Exception $e) {
            $this->logger->log(LogLevel::WARNING, $e->getMessage());
        }
    }
}
