<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Indexer;

use Pluswerk\Elasticsearch\Exception\ParseException;
use Pluswerk\Elasticsearch\Exception\TransportException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RssFeedIndexer extends AbstractElasticIndexer
{
    /**
     * @throws \Pluswerk\Elasticsearch\Exception\ParseException
     * @throws \Pluswerk\Elasticsearch\Exception\TransportException
     */
    public function process(): void
    {
        $client = $this->config->getClient();
        if ($client === null) {
            return;
        }

        $results = $this->getFeed();

        $params = [];
        $iterator = 0;

        $this->output->writeln(sprintf('<info>Indexing %s entities of %s...</info>', count($results), $this->tableName));

        foreach ($results as $result) {
            $iterator++;
            $id = $this->extractId($result);

            $params['body'][] = $this->getIndexBody($id);
            $documentBody = $this->getDocumentBody($result);

            $params['body'][] = $documentBody;

            // Every 1000 documents stop and send the bulk request
            if ($iterator % 1000 === 0) {
                $client->bulk($params);

                // erase the old bulk request
                $params = ['body' => []];
            }
        }

        if (!empty($params['body'])) {
            $client->bulk($params);
        }
    }

    /**
     * @return array<int,array<string,string>>
     * @throws \Pluswerk\Elasticsearch\Exception\ParseException
     * @throws \Pluswerk\Elasticsearch\Exception\TransportException
     */
    protected function getFeed(): array
    {
        $uri = $this->config->getConfigForTable($this->index, $this->tableName)['uri'] ?? '';
        if (!parse_url($uri)) {
            throw new TransportException('Uri not valid ' . $uri);
        }
        $mapping = $this->config->getFieldMappingForTable($this->index, $this->tableName);

        $content = GeneralUtility::getUrl($uri);
        if (!$content) {
            throw new TransportException('Could not GET uri ' . $uri);
        }

        if (!($x = simplexml_load_string($content))) {
            throw new ParseException('Could not parse uri ' . $uri);
        }

        $this->output->writeln(sprintf('<info>Connected to %s - %s</info>', $x->channel->title ?? '', $x->channel->description ?? ''));
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
