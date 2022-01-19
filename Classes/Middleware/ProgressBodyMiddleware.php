<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Middleware;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Indexer\PageIndexer;
use Pluswerk\Elasticsearch\Indexer\PdfIndexer;
use Pluswerk\Elasticsearch\Transformer\RequestTransformer;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

class ProgressBodyMiddleware
{
    /** @var callable */
    private $nextHandler;

    private PageIndexer $pageIndexer;

    private PdfIndexer $pdfIndexer;

    public function __construct(callable $nextHandler)
    {
        $this->nextHandler = $nextHandler;
    }

    /**
     * @param \GuzzleHttp\Psr7\Request $request
     * @param array $options
     * @return \GuzzleHttp\Promise\PromiseInterface
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     * @throws \TYPO3\CMS\Core\Routing\RouteNotFoundException
     */
    public function __invoke(Request $request, array $options): PromiseInterface
    {
        $fn = $this->nextHandler;

        $serverRequest = GeneralUtility::makeInstance(RequestTransformer::class)->transformPsrRequestToServerRequest($request);
        $pageUid = $serverRequest->getAttribute('arguments')->getPageId();

        $elasticConfig = ElasticConfig::byRequest($serverRequest);

        $this->pageIndexer = GeneralUtility::makeInstance(PageIndexer::class, $elasticConfig, 'pages');
        $this->pdfIndexer = GeneralUtility::makeInstance(PdfIndexer::class, $elasticConfig, 'pdf', $elasticConfig->getIndexName());

        $deleted = $elasticConfig->getClient()->deleteByQuery(
            [
                'index' => $elasticConfig->getIndexName(),
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'match' => [
                                        'pid' => $pageUid,
                                    ],
                                ],
                                [
                                    'match' => [
                                        'type' => 'pdf',
                                    ],
                                ],
                            ],
                        ],

                    ],
                ],
            ]
        )['deleted'] ?? 0;

        $output = new ConsoleOutput();
        $output->write(' Deleted ' . $deleted . ' documents');

        /** @var \GuzzleHttp\Promise\FulfilledPromise $response */
        $response = $fn($request, $options);

        $response->then(
            function (ResponseInterface $response) use ($pageUid, $serverRequest) {
                $code = $response->getStatusCode();
                $page = GeneralUtility::makeInstance(PageRepository::class)->getPage($pageUid, true);
                $remove = true;

                if ($code === 200) {
                    $content = $response->getBody()->getContents();
                    $content = $this->pageIndexer->getIndexableContent($content);
                    $this->pdfIndexer->setContent($content)->setPid($pageUid)->process();

                    if ($content) {
                        $page['content'] = $content;
                        $this->pageIndexer->indexContent($page, $serverRequest->getUri());
                        $remove = false;
                    }
                }
                if ($remove) {
                    $id = $this->pageIndexer->generateIdByDocument((string)$pageUid);
                    $this->pageIndexer->removeContentById($id);
                }
            }
        );
        return $response;
    }

    protected function getTypoScriptFrontendController(): ?TypoScriptFrontendController
    {
        return GeneralUtility::makeInstance(TypoScriptFrontendController::class);
    }
}
