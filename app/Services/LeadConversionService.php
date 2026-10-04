<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ContactRepository;
use App\Contracts\Repositories\LeadRepository;
use App\DataObjects\ConvertLeadData;
use App\DataObjects\LeadConversionResult;
use App\Enums\ContactStatus;
use App\Enums\LeadStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

/**
 * Lead -> Contact (+ optional Deal), atomically.
 * Kept apart from LeadService because it orchestrates three aggregates.
 */
final class LeadConversionService
{
    public function __construct(
        private readonly LeadRepository $leads,
        private readonly ContactRepository $contacts,
        private readonly ContactService $contactService,
        private readonly DealService $dealService,
    ) {}

    public function convert(Lead $lead, ConvertLeadData $options): LeadConversionResult
    {
        return DB::transaction(function () use ($lead, $options) {
            $locked = $this->leads->findForUpdate($lead->getKey());

            if ($locked->status->isConverted()) {
                throw new BusinessRuleException('This lead has already been converted.');
            }

            if (! $locked->status->canConvert()) {
                throw new BusinessRuleException(
                    "Only qualified leads can be converted (current status: {$locked->status->value}).",
                    ['status' => $locked->status->value, 'required' => LeadStatus::Qualified->value],
                );
            }

            [$contact, $contactCreated] = $this->resolveContact($locked);

            $deal = $options->createDeal
                ? $this->dealService->create([
                    'title' => $options->dealTitle ?? "{$locked->full_name} – Opportunity",
                    'amount' => $options->dealAmount ?? $locked->estimated_value ?? '0.00',
                    'currency' => $locked->currency,
                    'contact_id' => $contact->getKey(),
                    'expected_close_date' => $options->expectedCloseDate,
                ])
                : null;

            $converted = $this->leads->update($locked, [
                'status' => LeadStatus::Converted,
                'converted_contact_id' => $contact->getKey(),
                'converted_at' => now(),
            ]);

            return new LeadConversionResult($converted, $contact, $deal, $contactCreated);
        });
    }

    /**
     * Re-use a live contact with the same email instead of creating a duplicate.
     *
     * @return array{0: Contact, 1: bool} [contact, wasCreated]
     */
    private function resolveContact(Lead $lead): array
    {
        $existing = $this->contacts->findByEmail($lead->email, withTrashed: true);

        if ($existing?->trashed()) {
            // contacts.email is unique at DB level, so a deleted row would block the insert.
            throw new BusinessRuleException(
                'A deleted contact with this email already exists. Restore it before converting this lead.',
                ['email' => $lead->email],
            );
        }

        if ($existing !== null) {
            return [$existing, false];
        }

        return [
            $this->contactService->create([
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company' => $lead->company,
                'status' => ContactStatus::Active,
            ]),
            true,
        ];
    }
}
