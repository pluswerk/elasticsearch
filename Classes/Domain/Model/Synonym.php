<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Domain\Model;

use Pluswerk\Elasticsearch\Domain\Repository\SynonymRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Container\Container;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Synonym extends AbstractModel
{
    protected string $title = '';

    /**
     * @var ObjectStorage<Term>
     */
    protected ObjectStorage $terms;

    protected bool $self = false;

    protected array $raw = [];

    public function getFilters(): array
    {
        return array_filter(GeneralUtility::trimExplode(',', $this->raw()['filters'] ?? ''));
    }

    protected function raw(): array
    {
        if ($this->raw) {
            return $this->raw;
        }
        $container = GeneralUtility::makeInstance(Container::class);
        $repository = $container->getInstance(SynonymRepository::class);
        $this->raw = $repository->findOneRawBySynonym($this);
        return $this->raw;
    }

    public function initializeObject(): void
    {
        $this->terms = new ObjectStorage();
    }

    public function getLanguageUid(): int
    {
        return $this->_languageUid;
    }

    /**
     * @return Term[]
     * @noinspection PhpDocSignatureInspection
     */
    public function getTerms(): ObjectStorage
    {
        return $this->terms;
    }

    public function isSelf(): bool
    {
        return $this->self;
    }

    public function __toString()
    {
        return parent::__toString() . '/' . $this->getTitle();
    }

    public function getTitle(): string
    {
        return $this->title;
    }
}
