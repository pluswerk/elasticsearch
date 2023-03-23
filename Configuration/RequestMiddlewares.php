<?php

return [
    'frontend' => [
        'elasticsearch/service/pageexporter' => [
            'target' => \Pluswerk\Elasticsearch\Middleware\ElasticPageMiddleware::class,
            'before' => [
                'typo3/cms-frontend/output-compression'
            ]
        ]
    ]
];
