<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Indexer;

abstract class AbstractUriContentIndexer extends AbstractElasticIndexer
{
    abstract protected function getResults();

    public function process(): void
    {
        $client = $this->config->getClient();
        if ($client === null) {
            return;
        }

        $results = $this->getResults();

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
}
