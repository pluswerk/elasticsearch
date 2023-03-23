<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Hook;

use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class TceMainHook
{
    public static function register(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = static::class;
    }

    /**
     * @param $incomingFieldArray
     * @param $table
     * @param $id
     * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
     * @codingStandardsIgnoreStart
     */
    public function processDatamap_preProcessFieldArray(&$incomingFieldArray, $table, $id, DataHandler $dataHandler): void
    {
        // @codingStandardsIgnoreEnd
        if (strpos($table, 'tx_elasticsearch_domain_model_') !== 0) {
            return;
        }

        if (isset($incomingFieldArray['title'])) {
            $incomingFieldArray['title'] = implode(', ', array_unique(array_filter(GeneralUtility::trimExplode(',', $incomingFieldArray['title']))));
        }
    }
}
