<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\DataProcessor;

use Pluswerk\Elasticsearch\Config\ElasticConfig;
use Pluswerk\Elasticsearch\Exception\ClientNotAvailableException;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

class ElasticsearchDataProcessor implements DataProcessorInterface
{
    public function process(ContentObjectRenderer $cObj, array $contentObjectConfiguration, array $processorConfiguration, array $processedData): array
    {
        $key = $processorConfiguration['as'] ?? 'elasticsearch';

        /** @var \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController $tsfe */
        $tsfe = $GLOBALS['TSFE'];
        try {
            $conf = ElasticConfig::bySiteAndLanguage($tsfe->getSite(), $tsfe->getLanguage());
        } catch (ClientNotAvailableException $e) {
            if (class_exists(LogManager::class)) {
                $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
                $logger->emergency($e->getMessage());
            }
            return $processedData;
        }
        $uri = $conf->getPublicUrl();
        $processedData['data'][$key] = ['uri' => $uri];
        return $processedData;
    }
}
