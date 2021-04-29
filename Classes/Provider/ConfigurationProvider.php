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

        $fieldMapping = $config->getFieldMapping();
        $analyzers = $config->getAnalyzers();
        foreach ($items as $item) {
            $fields = [];
            foreach ($analyzers as $analyzerName => $analyzer) {
                if (!isset($analyzer['filter'])) {
                    continue;
                }
                if (in_array($item, $analyzer['filter'], true)) {
                    foreach ($fieldMapping as $fieldName => $fieldMap) {
                        if ((isset($fieldMap['analyzer']) && $fieldMap['analyzer'] === $analyzerName)
                            || (isset($fieldMap['search_analyzer']) && $fieldMap['search_analyzer'] === $analyzerName)) {
                            $fields[] = $fieldName;
                        }
                    }
                }
            }

            $label = implode(', ', array_unique($fields));
            if ($label) {
                $label .= ' ';
            }
            $label .= '(' . $item . ')';

            $configuration['items'][] = [$label, $item];
        }
    }
}
