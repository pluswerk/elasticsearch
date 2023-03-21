<?php

defined('TYPO3_MODE') or die('Access denied.');

call_user_func(function () {
    \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
        'Pluswerk.Elasticsearch',
        'Elasticsearch',
        [
            'Search' => 'search',
        ],
        // non-cacheable actions
        [
            'Search' => 'search',
        ]
    );
    \Pluswerk\Elasticsearch\Hook\TceMainHook::register();
});
