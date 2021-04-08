<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Controller;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class SearchController extends ActionController
{

    /**
     * @return void
     * TODO check
     */
    public function searchAction(Request $request)
    {
        $q = strtolower(GeneralUtility::_GET('q') ?? '');

        $elasticConfig = ElasticConfig::byRequest($request);
        $searchParams = [
            'index' => $elasticConfig->getIndexName(),
            'body' => [
                'query' => [
                    'multi_match' => [
                        'query' => $q,
                        'fields' => $elasticConfig->getSearchFields(),
                    ]
                ],
            ],
        ];

        $client = $elasticConfig->getClient();
        if (null !== $client) {
            $results = $client->search($searchParams);
            if ($results['hits']['hits']) {
                header('Content-Type: application/json');
                echo json_encode($results['hits']['hits']);
                exit;
            }
        }

        echo '{}';
        exit;
    }

    private function getTypoScriptFrontendController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }
}
