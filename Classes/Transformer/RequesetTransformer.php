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

class RequesetTransformer
{
    public function transformPsrRequestToServerRequest(RequestInterface $request): ServerRequestInterface
    {
        $matcher = GeneralUtility::makeInstance(SiteMatcher::class);
        $serverRequest = GeneralUtility::makeInstance(ServerRequest::class, $request->getMethod(), $request->getUri());

        /** @var SiteRouteResult $routeResult */
        $routeResult = $matcher->matchRequest($serverRequest);

        $router = GeneralUtility::makeInstance(PageRouter::class,$routeResult->getSite());
        $siteRouteResult = GeneralUtility::makeInstance(SiteRouteResult::class, $serverRequest->getUri(), $routeResult->getSite(), $routeResult->getLanguage(), $routeResult->getTail());

        /** @var PageArguments $pageArguments */
        $pageArguments = $router->matchRequest($serverRequest, $siteRouteResult);

        $serverRequest = $serverRequest->withAttribute('site', $routeResult->getSite());
        $serverRequest = $serverRequest->withAttribute('language', $routeResult->getLanguage());
        $serverRequest = $serverRequest->withAttribute('routing', $routeResult);
        $serverRequest = $serverRequest->withAttribute('routing', $routeResult);
        $serverRequest = $serverRequest->withAttribute('arguments', $pageArguments);
        if ($routeResult->getLanguage() instanceof SiteLanguage) {
            Locales::setSystemLocaleFromSiteLanguage($routeResult->getLanguage());
        }
        return $serverRequest;
    }
}
