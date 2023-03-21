<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Indexer;

use Pluswerk\Elasticsearch\Exception\MappingException;
use Pluswerk\Elasticsearch\Exception\ParseException;

class RssFeedIndexer extends AbstractIndexer
{
    /**
     * @return array<int,array<string,string>>
     * @throws \Pluswerk\Elasticsearch\Exception\ParseException
     * @throws \Pluswerk\Elasticsearch\Exception\TransportException
     */
    protected function getContent(): array
    {
        $content = parent::getContent();

        if (!($x = simplexml_load_string($content))) {
            throw new ParseException('Could not parse content');
        }

        $this->logger->notice(sprintf('<info>Connected to %s - %s</info>', $x->channel->title ?? '', $x->channel->description ?? ''));

        $mapping = $this->config->getFieldMappingForTable($this->tableName);
        $feeds = [];
        foreach ($x->item as $item) {
            $feed = [];
            foreach ($mapping as $elasticName => $rssName) {
                if (!isset($item->{$rssName})) {
                    throw new MappingException('Could not map field ' . $rssName);
                }
                $feed[$rssName] = (string)$item->{$rssName};
            }

            $feeds[] = $feed;
        }
        return $feeds;
    }
}
