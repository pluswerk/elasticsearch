<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Domain\Repository;

use Pluswerk\Elasticsearch\Domain\Model\Synonym;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @method Synonym[] findAll()
 */
class SynonymRepository extends Repository
{
    public function __construct(ObjectManagerInterface $objectManager, Typo3QuerySettings $defaultQuerySettings)
    {
        parent::__construct($objectManager);
        $defaultQuerySettings->setRespectStoragePage(false);
        $defaultQuerySettings->setRespectSysLanguage(false);
        $this->defaultQuerySettings = $defaultQuerySettings;
    }
}
