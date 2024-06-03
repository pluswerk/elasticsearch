<?php

return [
    'elasticsearch:create-indices' => [
        'class' => \Pluswerk\Elasticsearch\Command\CreateIndexCommand::class
    ],
    'elasticsearch:index-records' => [
        'class' => \Pluswerk\Elasticsearch\Command\IndexRecordsCommand::class
    ],
    'elasticsearch:export-synonyms' => [
        'class' => \Pluswerk\Elasticsearch\Command\ExportSynonymsCommand::class
    ]
];
