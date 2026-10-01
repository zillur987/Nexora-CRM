<?php

namespace App\Data;

use App\Enums\ContactStatus;
use App\Http\Requests\Contacts\StoreContactRequest;

final readonly class ContactData
{
    public function __construct(
        public string $firstName,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $jobTitle = null,
        public ?int $companyId = null,
        public ?int $ownerId = null,
        public ContactStatus $status = ContactStatus::Active,
        public ?string $source = null,
        public array $customFields = [],
    ) {}

    public static function fromRequest(StoreContactRequest $request): self
    {
        $v = $request->validated();

        return new self(
            firstName: $v['first_name'],
            lastName: $v['last_name'] ?? null,
            email: $v['email'] ?? null,
            phone: $v['phone'] ?? null,
            jobTitle: $v['job_title'] ?? null,
            companyId: $v['company_id'] ?? null,
            ownerId: $v['owner_id'] ?? $request->user()->id,
            status: ContactStatus::tryFrom($v['status'] ?? '') ?? ContactStatus::Active,
            source: $v['source'] ?? null,
            customFields: $v['custom_fields'] ?? [],
        );
    }
}