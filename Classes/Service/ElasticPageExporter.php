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
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class ElasticPageExporter implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var ElasticConfig
     */
    protected $elasticConfig;

    public function __construct()
    {
        /** @var \TYPO3\CMS\Core\Http\Request $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        $this->elasticConfig = ElasticConfig::byRequest($request);
    }

    public function getIndexableContent(string $html): string
    {
        if (!strpos($html, '<!--TYPO3SEARCH_begin-->') || !strpos($html, '<!--TYPO3SEARCH_end-->')) {
            return '';
        }

        $html = substr($html, strpos($html, '<!--TYPO3SEARCH_begin-->') + strlen('<!--TYPO3SEARCH_begin-->'));
        $html = substr($html, 0, strpos($html, '<!--TYPO3SEARCH_end-->'));

        if ($html) {
            return $html;
        }

        return '';
    }

    protected function getTypoScriptFrontendController(): ?TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    public function indexContent(string $content, UriInterface $url): void
    {
        $page = $this->getTypoScriptFrontendController()->page;
        $page['content'] = $content;
        $page['url'] = $url->getPath();
        $pagesMapping = $this->elasticConfig->getFieldMappingForTable($this->elasticConfig->getIndexName(), 'pages');
        foreach ($pagesMapping as $elasticField => $typoField) {
            $indexBody[$elasticField] = $page[$typoField] ?? '';
        }

        $indexBody['id'] = 'pages:' . $page['uid'];

        $indexingParameters = [
            'index' => $this->elasticConfig->getIndexName(),
            'id' => 'pages:' . $page['uid'],
            'body' => $indexBody
        ];

        try {
            $client = $this->elasticConfig->getClient();
            if (null === $client) {
                throw new ClientNotAvailableException();
            }
            $client->index($indexingParameters);
        } catch (Exception $e) {
            $this->logger->log(LogLevel::WARNING, $e->getMessage());
        }
    }
}
