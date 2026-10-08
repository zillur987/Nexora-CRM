<?php

declare(strict_types=1);

namespace App\Data;

/** Outcome of a CSV import: how many leads were created and why the other rows were skipped. */
final readonly class LeadImportResult
{
    /**
     * @param  list<array{row: int, message: string}>  $errors  `row` is the line number in the file (the header is row 1)
     */
    public function __construct(
        public int $created,
        public array $errors = [],
    ) {}

    /** @return array{created: int, skipped: int, errors: list<array{row: int, message: string}>} */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'skipped' => count($this->errors),
            'errors' => $this->errors,
        ];
    }
}
