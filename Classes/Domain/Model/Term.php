<?php

declare(strict_types=1);

namespace Pluswerk\Elasticsearch\Domain\Model;

class Term extends AbstractModel
{
    protected string $title = '';

    public function getTitle(): string
    {
        return $this->title;
    }
}
