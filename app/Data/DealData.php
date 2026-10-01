<?php // app/Data/DealData.php

namespace App\Data;

use App\Http\Requests\Deals\StoreDealRequest;

final readonly class DealData
{
    public function __construct(
        public int $pipelineId,
        public ?int $stageId,
        public string $title,
        public int $amount = 0,
        public string $currency = 'USD',
        public ?int $contactId = null,
        public ?int $companyId = null,
        public ?int $ownerId = null,
        public ?string $expectedCloseDate = null,
        public array $customFields = [],
    ) {}

    public static function fromRequest(StoreDealRequest $request): self
    {
        $v = $request->validated();

        return new self(
            pipelineId: $v['pipeline_id'],
            stageId: $v['stage_id'] ?? null,
            title: $v['title'],
            amount: $v['amount'] ?? 0,
            currency: strtoupper($v['currency'] ?? 'USD'),
            contactId: $v['contact_id'] ?? null,
            companyId: $v['company_id'] ?? null,
            ownerId: $v['owner_id'] ?? $request->user()->id,
            expectedCloseDate: $v['expected_close_date'] ?? null,
            customFields: $v['custom_fields'] ?? [],
        );
    }
}