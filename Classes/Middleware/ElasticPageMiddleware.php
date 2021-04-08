<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Middleware;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Service\ElasticPageExporter;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class ElasticPageMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var Site
     */
    protected $site;

    /**
     * @var ElasticConfig
     */
    protected $elasticConfig;

    protected ElasticPageExporter $elasticPageExporter;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->elasticPageExporter = GeneralUtility::getContainer()->get(ElasticPageExporter::class);
        /** @var \TYPO3\CMS\Core\Http\Response $response */
        $response = $handler->handle($request);

        /** @var \TYPO3\CMS\Core\Site\Entity\SiteLanguage $siteLanguage */
        $siteLanguage = $request->getAttribute('language');

        $index = $siteLanguage->toArray()['elasticsearch']['index'] ?? '';
        if ($this->isPageIndexable($request, $response) && $this->elasticConfig !== null && $this->elasticConfig->isMiddlewareProcessingAllowed($index)) {
            $html = $this->elasticPageExporter->getIndexableContent($this->getTypoScriptFrontendController()->content);

            if ($html === '') {
                return $response;
            }

            $this->elasticPageExporter->indexContent($html, $request->getUri());
        }

        return $response;
    }

    protected function isPageIndexable(ServerRequestInterface $request, Response $response): bool
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

        $this->elasticConfig = ElasticConfig::byRequest($request);

        return true;
    }

    protected function getTypoScriptFrontendController(): ?TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }
}
