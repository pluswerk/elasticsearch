<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Transformer;

use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Request;

class RequestTransformer
{
    /**
     * Uses the PSR Request with its URL to create a Server Request
     * Feeds the Request with "site, routing, language and arguments"
     *
     * @param \Psr\Http\Message\RequestInterface $request
     * @return \Psr\Http\Message\ServerRequestInterface
     * @throws \TYPO3\CMS\Core\Routing\RouteNotFoundException
     */
    public function transformPsrRequestToServerRequest(RequestInterface $request): ServerRequestInterface
    {
        $serverRequest = GeneralUtility::makeInstance(ServerRequest::class, $request->getMethod(), $request->getUri());
        return $this->enrichtRequest($serverRequest);
    }

    /**
     * Uses the PSR Request with its URL to create a Server Request
     * Feeds the Request with "site, routing, language and arguments"
     *
     * @param \TYPO3\CMS\Extbase\Mvc\Request $request
     * @return \Psr\Http\Message\ServerRequestInterface
     * @throws \TYPO3\CMS\Core\Routing\RouteNotFoundException
     */
    public function transformExtbaseMvcRequestToServerRequest(Request $request): ServerRequestInterface
    {
        $serverRequest = GeneralUtility::makeInstance(ServerRequest::class, $request->getMethod(), $request->getRequestUri());
        return $this->enrichtRequest($serverRequest);
    }

    /**
     * @throws \TYPO3\CMS\Core\Routing\RouteNotFoundException
     */
    protected function enrichtRequest(ServerRequestInterface $serverRequest): ServerRequestInterface
    {
        $matcher = GeneralUtility::makeInstance(SiteMatcher::class);
        /** @var SiteRouteResult $routeResult */
        $routeResult = $matcher->matchRequest($serverRequest);

        $router = GeneralUtility::makeInstance(PageRouter::class,$routeResult->getSite());
        $siteRouteResult = GeneralUtility::makeInstance(SiteRouteResult::class, $serverRequest->getUri(), $routeResult->getSite(), $routeResult->getLanguage(), $routeResult->getTail());

        /** @var PageArguments $pageArguments */
        $pageArguments = $router->matchRequest($serverRequest, $siteRouteResult);

        $serverRequest = $serverRequest->withAttribute('site', $routeResult->getSite());
        $serverRequest = $serverRequest->withAttribute('language', $routeResult->getLanguage());
        $serverRequest = $serverRequest->withAttribute('routing', $routeResult);
        $serverRequest = $serverRequest->withAttribute('arguments', $pageArguments);
        if ($routeResult->getLanguage() instanceof SiteLanguage) {
            Locales::setSystemLocaleFromSiteLanguage($routeResult->getLanguage());
        }
        return $serverRequest;
    }
}
