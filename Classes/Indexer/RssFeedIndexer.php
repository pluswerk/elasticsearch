<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Indexer;

use Pluswerk\Elasticsearch\Exception\ParseException;

class RssFeedIndexer extends AbstractUriContentIndexer
{
    /**
     * @return array<int,array<string,string>>
     * @throws \Pluswerk\Elasticsearch\Exception\ParseException
     * @throws \Pluswerk\Elasticsearch\Exception\TransportException
     */
    protected function getResults(): array
    {
        $content = $this->getContent();

        if (!($x = simplexml_load_string($content))) {
            throw new ParseException('Could not parse content');
        }

        $this->output->writeln(sprintf('<info>Connected to %s - %s</info>', $x->channel->title ?? '', $x->channel->description ?? ''));

        $mapping = $this->config->getFieldMappingForTable($this->index, $this->tableName);
        $feeds = [];
        foreach ($x->channel->item as $item) {
            $feed = [];
            foreach ($mapping as $elasticName => $rssName) {
                $feed[$rssName] = (string)$item->{$rssName};
            }

            $feeds[] = $feed;
        }
        return $feeds;
    }
}