<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Indexer;

use Pluswerk\Elasticsearch\Exception\ParseException;

class JsonResultIndexer extends AbstractUriContentIndexer
{
    /**
     * @return array<int,array<string,string>>
     * @throws \Pluswerk\Elasticsearch\Exception\ParseException
     * @throws \Pluswerk\Elasticsearch\Exception\TransportException
     * @throws \JsonException
     */
    protected function getResults(): array
    {
        $content = $this->getContent();

        if (!($x = json_decode($content, false, 4, JSON_THROW_ON_ERROR))) {
            throw new ParseException('Could not parse content');
        }

        $mapping = $this->config->getFieldMappingForTable($this->index, $this->tableName);
        $feeds = [];
        foreach ($x->result as $item) {
            $feed = [];
            foreach ($mapping as $elasticName => $rssName) {
                if (in_array($elasticName, ['uid', 'type'])) {
                    $key = $elasticName;
                } else {
                    $key = $rssName;
                }
                $feed[$key] = (string)$item->{$rssName};
            }
            $feeds[] = $feed;
        }
        return $feeds;
    }
}
