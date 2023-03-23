<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Command;

use Pluswerk\Elasticsearch\Persistence\DumbSession;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Container\Container;
use TYPO3\CMS\Extbase\Persistence\Generic\Session;

abstract class AbstractCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        GeneralUtility::makeInstance(Container::class)
            ->registerImplementation(
                Session::class,
                DumbSession::class
            );
    }
}
