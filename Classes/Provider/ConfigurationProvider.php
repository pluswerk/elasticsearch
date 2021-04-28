<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Provider;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Container\Container;

class ConfigurationProvider
{
    /**
     * Adds all synonyms filters from the current configuration to items
     *
     * @param array $configuration Current field configuration
     * @throws \UnexpectedValueException
     * @throws \Pluswerk\Elasticsearch\Exception\InvalidConfigurationException
     * @throws \Pluswerk\Elasticsearch\Exception\ClientNotAvailableException
     */
    public function getSynonymFilters(array &$configuration): void
    {
        $container = GeneralUtility::makeInstance(Container::class);
        $siteFinder = $container->getInstance(SiteFinder::class);

        $row = $configuration['row'];
        $pid = $row['pid'];
        $sysLanguageUid = (int)$row['sys_language_uid'][0];

        $site = $siteFinder->getSiteByPageId($pid);
        $siteLanguage = $site->getLanguageById($sysLanguageUid);

        $config = ElasticConfig::bySiteAndLanguage($site, $siteLanguage);
        $filters = $config->getFilters();
        $filters = array_filter(
            $filters,
            static function (
                array $filter
            ) {
                return $filter['type'] === 'synonym';
            }
        );

        $items = array_keys($filters);

        foreach ($items as $item) {
            $configuration['items'][] = [$item, $item];
        }
    }
}
