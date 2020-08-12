<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Service;

use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\TypoScript\ExtendedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class FrontendSimulationService
{
    protected $initialized = false;

    public function initTSFE(int $id = 1, int $typeNum = 0): void
    {
        if ($this->initialized) {
            return;
        }

        /** @var \TYPO3\CMS\Frontend\Page\PageRepository $pageSelect */
        $rootLineUtility = GeneralUtility::makeInstance(RootlineUtility::class, $id);
        $rootLine = $rootLineUtility->get();

        $tmpl = GeneralUtility::makeInstance(ExtendedTemplateService::class);
        $tmpl->tt_track = false;
        $tmpl->runThroughTemplates($rootLine);

        if (!is_object($GLOBALS['TT'])) {
            $GLOBALS['TT'] = new TimeTracker(false);
            $GLOBALS['TT']->start();
        }

        $GLOBALS['TSFE'] = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $GLOBALS['TYPO3_CONF_VARS'], $id, $typeNum);

        $GLOBALS['TSFE']->sys_page = $pageSelect;
        $GLOBALS['TSFE']->initFEuser();
        $GLOBALS['TSFE']->determineId();
        $GLOBALS['TSFE']->cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $this->initialized = true;
    }

    public function reset(): void
    {
        $GLOBALS['TT'] = null;
        $GLOBALS['TSFE'] = null;
        $this->initialized = false;
    }
}
