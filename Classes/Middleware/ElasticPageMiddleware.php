<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Middleware;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Service\PageIndexer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
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

    protected PageIndexer $pageIndexer;

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var \TYPO3\CMS\Core\Http\Response $response */
        $response = $handler->handle($request);

        $elasticConfig = ElasticConfig::byRequest($request);
        if (null === $elasticConfig || !$elasticConfig->isMiddlewareProcessingAllowed()) {
            return $response;
        }

        if ($this->isPageIndexable($request, $response)) {

            $this->pageIndexer = GeneralUtility::makeInstance(PageIndexer::class, $elasticConfig, 'pages');
            $html = $this->pageIndexer->getIndexableContent($this->getTypoScriptFrontendController()->content);

            if ($html === '') {
                return $response;
            }

            $page = $this->getTypoScriptFrontendController()->page;
            $page['content'] = $html;
            $this->pageIndexer->indexContent($page, $request->getUri());
        }

        return $response;
    }

    protected function isPageIndexable(ServerRequestInterface $request, Response $response): bool
    {
        $tsfe = $this->getTypoScriptFrontendController();
        if (
            !($response instanceof Response)
            || !($tsfe instanceof TypoScriptFrontendController)
            || $tsfe->page['no_index'] === 1
            || $tsfe->page['no_follow'] === 1
            || $tsfe->page['hidden'] === 1
            || !empty(
                $tsfe->page['fe_group']
                || !$tsfe->isOutputting()
            )
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

        if (!($request->getAttributes()['site'] instanceof Site)) {
            return false;
        }

        return true;
    }

    protected function getTypoScriptFrontendController(): ?TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }
}
