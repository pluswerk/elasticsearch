<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Controller;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class SearchController extends ActionController
{

    /**
     * @return void
     */
    public function searchAction()
    {
        $q = strtolower(GeneralUtility::_GET('q') ?? '');
        $sitefinder = GeneralUtility::makeInstance(SiteFinder::class);
        $currentSite = $sitefinder->getSiteByPageId($this->getTypoScriptFrontendController()->id);

        /** @var ElasticConfig $elasticConfig */
        $elasticConfig = GeneralUtility::makeInstance(ElasticConfig::class, $currentSite);

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

        $results = $elasticConfig->getClient()->search($searchParams);
        if ($results['hits']['hits']) {
            header('Content-Type: application/json');
            echo json_encode($results['hits']['hits']);
            exit;
        }


        echo '{}';
        exit;
    }

    private function getTypoScriptFrontendController(): TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }
}
