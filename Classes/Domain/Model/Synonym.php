<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Domain\Model;

use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Synonym extends AbstractModel
{
    protected string $title = '';

    /**
     * @var ObjectStorage<Term>
     */
    protected ObjectStorage $terms;

    protected bool $self = false;

    public function initializeObject(): void
    {
        $this->terms = new ObjectStorage();
    }

    public function getLanguageUid(): int
    {
        return $this->_languageUid;
    }

    public function getTitle(): string
    {
        return $this->title;
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
}
