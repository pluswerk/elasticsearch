<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Service;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LogLevel;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class ElasticPageExporter implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Site
     */
    private $site;

    /**
     * @var ElasticConfig
     */
    private $elasticConfig;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var \TYPO3\CMS\Core\Http\Response $response */
        $response = $handler->handle($request);

        if ($this->isPageIndexable($request, $response) && $this->elasticConfig !== null && $this->elasticConfig->isMiddlewareProcessingAllowed()) {
            $html = $this->getIndexableContent($this->getTypoScriptFrontendController()->content);

            if ($html === '') {
                return $response;
            }

            $this->indexContent($html, $request->getUri());
        }

        return $response;
    }

    private function isPageIndexable(ServerRequestInterface $request, Response $response): bool
    {

        $tsfe = $this->getTypoScriptFrontendController();
        if (
            !($response instanceof Response)
            || !($tsfe instanceof TypoScriptFrontendController)
            || !$tsfe->isOutputting()
            || $tsfe->page['no_index'] === 1
            || $tsfe->page['no_follow'] === 1
            || $tsfe->page['hidden'] === 1
            || !empty($tsfe->page['fe_group'])
        ) {
            return false;
        }

        foreach ($response->getHeader('Content-Type') as $contentType) {
            if (strpos($contentType, 'text/html') !== 0) {
                return false;
            }
        }

        /** @var PageArguments $pageArguments */
        $pageArguments = $request->getAttributes()['routing'];
        if (count($pageArguments->getStaticArguments()) || count($pageArguments->getDynamicArguments())) {
            return false;
        }

        /** @var Site $site */
        $this->site = $request->getAttributes()['site'];

        if (!($this->site instanceof Site)) {
            return false;
        }

        if (!isset($this->site->getConfiguration()['elasticsearch'])) {
            return false;
        }

        $this->elasticConfig = GeneralUtility::makeInstance(ElasticConfig::class, $this->site);

        return true;
    }

    private function getIndexableContent(string $html): string
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

    private function getTypoScriptFrontendController(): ?TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    private function indexContent(string $content, UriInterface $url): void
    {
        $page = $this->getTypoScriptFrontendController()->page;
        $page['content'] = $content;
        $page['url'] = $url->getPath();
        $pagesMapping = $this->elasticConfig->getFieldMappingForTable('pages');
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
            $this->elasticConfig->getClient()->index($indexingParameters);
        } catch (\Exception $e) {
            $this->logger->log(LogLevel::WARNING, $e->getMessage());
        }
    }
}
