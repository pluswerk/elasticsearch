<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Persistence;

use TYPO3\CMS\Extbase\Persistence\Generic\Session;

class DumbSession extends Session
{
    public function hasObject($object): bool
    {
        return false;
    }

    public function hasIdentifier($identifier, $className): bool
    {
        return false;
    }

    public function registerObject($object, $identifier)
    {
    }

    public function unregisterObject($object)
    {
    }
}
