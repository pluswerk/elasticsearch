<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Config;

use Elasticsearch\Client;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

class RemoteElasticConfig extends ElasticConfig
{
    protected Site $site;
    protected Client $client;
    protected SiteLanguage $siteLanguage;

    /**
     * @return array<int, string>
     */
    public function getServerConfig(): array
    {
        return [getenv('PRIVATE_HOST_ELASTIC_WEB')];
    }

    public function getPublicHost(): ?string
    {
        $val = getenv('PUBLIC_HOST_ELASTIC_WEB');
        return $val ? (string)$val : null;
    }
}
