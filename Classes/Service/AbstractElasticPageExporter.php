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
        if (!strpos($html, '<!--TYPO3SEARCH_begin-->') || !strpos($html, '<!--TYPO3SEARCH_end-->')) {
            return '';
        }

        $html = substr($html, strpos($html, '<!--TYPO3SEARCH_begin-->') + strlen('<!--TYPO3SEARCH_begin-->'));
        $html = substr($html, 0, strpos($html, '<!--TYPO3SEARCH_end-->'));

        if ($html) {
            return trim($html);
        }

        return '';
    }

    public function generateIdByDocument(string $identifier): string
    {
        return 'pages:' . $identifier;
    }

    public function removeContentById(string $id)
    {
        var_dump("removing: " . $id);
        $this->elasticConfig->getClient()->delete([
            'id'=> $id,
            'index' => $this->elasticConfig->getIndexName()
        ]);
    }

    public function indexContent(array $page, UriInterface $url): void
    {
        var_dump("indexing");
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
var_dump($indexingParameters);
        try {
            $client = $this->elasticConfig->getClient();
            $client->index($indexingParameters);
        } catch (Exception $e) {
            $this->logger->log(LogLevel::WARNING, $e->getMessage());
        }
    }
}
