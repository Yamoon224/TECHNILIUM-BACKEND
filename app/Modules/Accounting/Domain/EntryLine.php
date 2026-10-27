<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain;

final readonly class EntryLine
{
    public function __construct(
        public string $account,
        public int $debit = 0,
        public int $credit = 0,
    ) {}

    public function reversed(): self
    {
        return new self($this->account, $this->credit, $this->debit);
    }
}
