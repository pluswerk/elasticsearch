<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Indexer;

use Pluswerk\Elasticsearch\Exception\MappingException;
use Pluswerk\Elasticsearch\Exception\ParseException;

class JsonResultIndexer extends AbstractIndexer
{
    /**
     * @return array<int,array<string,string>>
     * @throws \Pluswerk\Elasticsearch\Exception\ParseException
     * @throws \Pluswerk\Elasticsearch\Exception\TransportException
     * @throws \JsonException
     * @throws MappingException
     */
    protected function getContent(): array
    {
        $content = parent::getContent();

        if (!($x = json_decode($content, false, 4, JSON_THROW_ON_ERROR))) {
            throw new ParseException('Could not parse content');
        }

        $mapping = $this->config->getFieldMappingForTable($this->tableName);
        $feeds = [];
        foreach ($x->result as $item) {
            $feed = [];
            foreach ($mapping as $elasticName => $rssName) {
                if (in_array($elasticName, ['uid', 'type'])) {
                    $key = $elasticName;
                } else {
                    $key = $rssName;
                }
                if (!isset($item->{$rssName})) {
                    throw new MappingException('Could not map field ' . $key);
                }
                $feed[$key] = (string)$item->{$rssName};
            }
            $feeds[] = $feed;
        }
        return $feeds;
    }
}
