<?php

return [
    'frontend' => [
        'elasticsearch/service/pageexporter' => [
            'target' => \Pluswerk\Elasticsearch\Service\ElasticPageExporter::class,
            'before' => [
                'typo3/cms-frontend/output-compression'
            ]
        ]
    ]
];
