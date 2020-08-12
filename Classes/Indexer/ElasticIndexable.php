<?php

namespace Pluswerk\Elasticsearch\Indexer;

interface ElasticIndexable
{
    public function process(): void;
}
