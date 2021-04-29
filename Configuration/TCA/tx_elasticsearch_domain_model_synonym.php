<?php

return [
    'ctrl' => [
        'title' => 'LLL:EXT:elasticsearch/Resources/Private/Language/locallang_db.xlf:tx_elasticsearch_domain_model_synonym',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'dividers2tabs' => true,
        'versioningWS' => false,
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'title',
        'typeicon_classes' => [
            // TODO replace icon
            // @see https://docs.typo3.org/m/typo3/reference-tca/master/en-us/Ctrl/Properties/TypeiconClasses.html
            'default' => 'mimetypes-x-sys_category',
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:general,
                    title, filters, parent, terms, self,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
                    --palette--;;language,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    hidden
            ',
        ],
    ],
    'palettes' => [
        'language' => ['showitem' => 'sys_language_uid, l10n_parent'],
    ],
    'columns' => [
        'tstamp' => [
            'label' => 'tstamp',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'crdate' => [
            'label' => 'crdate',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'hidden' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden',
            'config' => [
                'type' => 'check',
            ],
        ],
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'special' => 'languages',
                'items' => [
                    [
                        'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages',
                        -1,
                        'flags-multiple',
                    ],
                ],
                'default' => 0,
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['', 0],
                ],
                'foreign_table' => 'tx_elasticsearch_domain_model_synonym',
                'foreign_table_where' => 'AND tx_elasticsearch_domain_model_synonym.pid=###CURRENT_PID### AND tx_elasticsearch_domain_model_synonym.sys_language_uid IN (-1,0)',
                'default' => 0,
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
                'default' => '',
            ],
        ],
        'self' => [
            'label' => 'LLL:EXT:elasticsearch/Resources/Private/Language/locallang_db.xlf:tx_elasticsearch_domain_model_synonym.self',
            'description' => 'LLL:EXT:elasticsearch/Resources/Private/Language/locallang_db.xlf:tx_elasticsearch_domain_model_synonym.description.self',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxLabeledToggle',
                'items' => [
                    [
                        0 => 0,
                        1 => 1,
                        'labelChecked' => 'LLL:EXT:elasticsearch/Resources/Private/Language/locallang_db.xlf:tx_elasticsearch_domain_model_synonym.self.enabled',
                        'labelUnchecked' => 'LLL:EXT:elasticsearch/Resources/Private/Language/locallang_db.xlf:tx_elasticsearch_domain_model_synonym.self.disabled',
                    ]
                ],
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:elasticsearch/Resources/Private/Language/locallang_db.xlf:tx_elasticsearch_domain_model_synonym.title',
            'description' => 'LLL:EXT:elasticsearch/Resources/Private/Language/locallang_db.xlf:tx_elasticsearch_domain_model_synonym.description.title',
            'config' => [
                'type' => 'input',
                'width' => 200,
                'eval' => 'trim,required',
            ],
        ],
        'filters' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:elasticsearch/Resources/Private/Language/locallang_db.xlf:tx_elasticsearch_domain_model_synonym.filters',
            'description' => 'LLL:EXT:elasticsearch/Resources/Private/Language/locallang_db.xlf:tx_elasticsearch_domain_model_synonym.description.filters',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingleBox',
                'itemsProcFunc' => \Pluswerk\Elasticsearch\Provider\ConfigurationProvider::class . '->getSynonymFilters',
            ],
        ],
        'terms' => [
            'label' => 'LLL:EXT:elasticsearch/Resources/Private/Language/locallang_db.xlf:tx_elasticsearch_domain_model_synonym.terms',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectMultipleSideBySide',
                'multiple' => 1,
                'foreign_table' => 'tx_elasticsearch_domain_model_term',
                'MM' => 'tx_elasticsearch_domain_model_synonym_term_mm',
                'foreign_table_where' => ' AND tx_elasticsearch_domain_model_term.pid=###CURRENT_PID### AND tx_elasticsearch_domain_model_term.sys_language_uid IN (-1,###REC_FIELD_sys_language_uid###) ORDER BY tx_elasticsearch_domain_model_term.title ',
                'minitems' => 1,
                'maxitems' => 99,
                'fieldControl' => [
                    'editPopup' => [
                        'disabled' => false,
                    ],
                    'addRecord' => [
                        'disabled' => false,
                    ],
                    'listModule' => [
                        'disabled' => true,
                    ],
                ],
            ],
        ],
    ],
];
