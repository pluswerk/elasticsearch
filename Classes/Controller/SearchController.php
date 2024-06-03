<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Controller;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Transformer\RequestTransformer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class SearchController extends ActionController
{
    protected RequestTransformer $requestTransformer;

    public function __construct(RequestTransformer $requestTransformer)
    {
        $this->requestTransformer = $requestTransformer;
    }

    /**
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     * @throws \TYPO3\CMS\Core\Routing\RouteNotFoundException
     */
    public function searchAction(): void
    {
        $q = strtolower(GeneralUtility::_GET('q') ?? '');

        $request = $this->request;
        if ($request instanceof Request) {
            $request = $this->requestTransformer->transformExtbaseMvcRequestToServerRequest($request);
        }

        $elasticConfig = ElasticConfig::byRequest($request);
        $searchParams = [
            'index' => $elasticConfig->getIndexName(),
            'body' => [
                'query' => [
                    'query_string' => [
                        'query' => $q,
                        'fields' => $elasticConfig->getSearchFields(),
                    ]
                ],
            ],
        ];

        $client = $elasticConfig->getClient();
        $results = $client->search($searchParams);
        if ($results['hits']['hits']) {
            header('Content-Type: application/json');
            /** @noinspection JsonEncodingApiUsageInspection */
            echo @json_encode($results['hits']['hits']);
            exit;
        }

        echo '{}';
        exit;
    }

    protected function getTypoScriptFrontendController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }
}
