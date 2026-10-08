<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\LeadImportResult;
use App\Http\Requests\Leads\Rules\LeadRules;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates leads from a CSV file.
 *
 * Valid rows are imported; invalid rows are skipped and reported with their line number,
 * so one typo never blocks the other 999 rows. A structurally broken file (no header,
 * missing required columns, too many rows) is rejected as a whole.
 */
final class LeadImportService
{
    public const MAX_ROWS = 1000;

    /** Columns that must be present in the header. */
    private const REQUIRED = ['first_name', 'last_name', 'email'];

    /** Every column we understand; anything else in the file is ignored. */
    private const KNOWN = [
        'first_name', 'last_name', 'email', 'phone', 'company', 'job_title',
        'source', 'score', 'estimated_value', 'currency', 'owner_email', 'notes',
    ];

    private const LOOKUP_CHUNK = 500;

    public function __construct(private readonly LeadService $leads) {}

    /** @throws ValidationException when the file as a whole cannot be imported */
    public function import(string $path): LeadImportResult
    {
        $rows = $this->readRows($path);

        $existing = $this->existingEmails($rows);
        $owners = $this->ownerIdsByEmail($rows);

        $valid = [];
        $errors = [];
        $firstSeen = [];

        foreach ($rows as $line => $row) {
            $outcome = $this->validateRow($row, $owners, $existing, $firstSeen);

            if (is_string($outcome)) {
                $errors[] = ['row' => $line, 'message' => $outcome];

                continue;
            }

            $firstSeen[$outcome['email']] = $line;
            $valid[] = $outcome;
        }
        // All-or-nothing for the rows that passed, so a mid-import failure leaves no half-imported file.
        DB::transaction(function () use ($valid): void {
            foreach ($valid as $attributes) {
                $this->leads->create($attributes);
            }
        });

        return new LeadImportResult(created: count($valid), errors: $errors);
    }

    /* ---------------------------------------------------------------------- */
    /* Per-row validation                                                      */
    /* ---------------------------------------------------------------------- */

    /**
     * @param  array<string, ?string>  $row
     * @param  array<string, int>  $owners  lower-cased user email => id
     * @param  array<string, true>  $existing  lower-cased emails already in the database
     * @param  array<string, int>  $firstSeen  email => line it first appeared on in this file
     * @return array<string, mixed>|string the clean attributes when valid, otherwise the reason the row is skipped
     */
    private function validateRow(array $row, array $owners, array $existing, array $firstSeen): array|string
    {
        $data = $this->normalize($row);
        $ownerId = null;

        if (isset($data['owner_email'])) {
            $ownerId = $owners[mb_strtolower($data['owner_email'])] ?? null;

            if ($ownerId === null) {
                return "No user found with the email \"{$data['owner_email']}\".";
            }
        }
        unset($data['owner_email']);

        // `assigned_to` is resolved above, so skip its per-row `exists` query.
        $validator = Validator::make($data, Arr::except(LeadRules::attributes(), 'assigned_to'));

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        $attributes = $validator->validated();
        $email = $attributes['email'];

        if (isset($existing[$email])) {
            return 'A lead with this email already exists.';
        }

        if (isset($firstSeen[$email])) {
            return "Duplicate of row {$firstSeen[$email]} in this file.";
        }

        return $ownerId !== null ? [...$attributes, 'assigned_to' => $ownerId] : $attributes;
    }

    /**
     * Same clean-up the create form gets (see StoreLeadRequest), plus CSV-friendly aliases.
     *
     * @param  array<string, ?string>  $row
     * @return array<string, mixed>
     */
    private function normalize(array $row): array
    {
        // Empty cells mean "not provided", so `sometimes` rules and column defaults apply.
        $data = array_filter($row, static fn (?string $value) => $value !== null);

        if (isset($data['email'])) {
            $data['email'] = mb_strtolower($data['email']);
        }
        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }
        if (isset($data['source'])) {
            // "Social media", "social-media" and "social_media" are all the same source.
            $data['source'] = preg_replace('/[\s\-]+/', '_', mb_strtolower($data['source']));
        }
        if (isset($data['estimated_value']) && preg_match('/^\d{1,3}(,\d{3})+(\.\d+)?$/', $data['estimated_value'])) {
            $data['estimated_value'] = str_replace(',', '', $data['estimated_value']);
        }

        return $data;
    }

    /* ---------------------------------------------------------------------- */
    /* Database lookups (one query per chunk instead of one per row)          */
    /* ---------------------------------------------------------------------- */

    /**
     * @param  array<int, array<string, ?string>>  $rows
     * @return array<string, true>
     */
    private function existingEmails(array $rows): array
    {
        $emails = $this->column($rows, 'email', lower: true);
        $found = [];

        foreach (array_chunk($emails, self::LOOKUP_CHUNK) as $chunk) {
            foreach (Lead::query()->whereIn('email', $chunk)->pluck('email') as $email) {
                $found[mb_strtolower($email)] = true;
            }
        }

        return $found;
    }

    /**
     * @param  array<int, array<string, ?string>>  $rows
     * @return array<string, int>
     */
    private function ownerIdsByEmail(array $rows): array
    {
        $emails = $this->column($rows, 'owner_email', lower: true);
        $found = [];

        foreach (array_chunk($emails, self::LOOKUP_CHUNK) as $chunk) {
            foreach (User::query()->whereIn('email', $chunk)->get(['id', 'email']) as $user) {
                $found[mb_strtolower($user->email)] = $user->id;
            }
        }

        return $found;
    }

    /**
     * @param  array<int, array<string, ?string>>  $rows
     * @return list<string>
     */
    private function column(array $rows, string $key, bool $lower): array
    {
        $values = [];

        foreach ($rows as $row) {
            if (($value = $row[$key] ?? null) !== null) {
                $values[] = $lower ? mb_strtolower($value) : $value;
            }
        }

        return array_values(array_unique($values));
    }

    /* ---------------------------------------------------------------------- */
    /* CSV reading                                                             */
    /* ---------------------------------------------------------------------- */

    /**
     * @return array<int, array<string, ?string>> rows keyed by their line number in the file
     *
     * @throws ValidationException
     */
    private function readRows(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            throw $this->fileError('That file is empty.');
        }

        $contents = $this->toUtf8($contents);
        $delimiter = $this->detectDelimiter($contents);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        try {
            $columns = $this->mapHeader(fgetcsv($stream, 0, $delimiter, '"', '') ?: []);
            $missing = array_diff(self::REQUIRED, $columns);

            if ($missing !== []) {
                throw $this->fileError('Missing required column(s): '.implode(', ', $missing).'.');
            }

            $rows = [];
            $line = 1; // the header

            while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                $line++;

                if ($cells === [null]) {
                    continue; // blank line
                }

                if (count($rows) >= self::MAX_ROWS) {
                    throw $this->fileError('A file can contain at most '.number_format(self::MAX_ROWS).' leads. Please split it into smaller files.');
                }

                $row = [];
                foreach ($columns as $index => $key) {
                    $row[$key] = $this->cleanCell($cells[$index] ?? null);
                }
                $rows[$line] = $row;
            }
        } finally {
            fclose($stream);
        }

        if ($rows === []) {
            throw $this->fileError('That file has a header but no leads.');
        }

        return $rows;
    }

    /**
     * Maps column position => known column name ("First Name" and "first_name" are the same column).
     *
     * @param  list<?string>  $header
     * @return array<int, string>
     */
    private function mapHeader(array $header): array
    {
        $columns = [];

        foreach ($header as $index => $name) {
            $key = preg_replace('/[\s\-]+/', '_', mb_strtolower(trim((string) $name)));

            if (in_array($key, self::KNOWN, true) && ! in_array($key, $columns, true)) {
                $columns[$index] = $key;
            }
        }

        return $columns;
    }

    private function cleanCell(?string $cell): ?string
    {
        $value = trim((string) $cell);

        // Our own CSV export prefixes cells that start with = + - @ with an apostrophe
        // (formula-injection guard), so "+1 555 0100" is written as "'+1 555 0100". Undo that.
        $value = preg_replace("/^'(?=[=+\\-@])/", '', $value);

        return $value === '' ? null : $value;
    }

    private function toUtf8(string $contents): string
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents); // Excel's UTF-8 BOM

        // Older Excel exports are Windows-1252, not UTF-8.
        return mb_check_encoding($contents, 'UTF-8')
            ? $contents
            : mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }

    /** Excel in many locales writes semicolons or tabs; pick whichever the header row uses most. */
    private function detectDelimiter(string $contents): string
    {
        $header = strtok($contents, "\r\n") ?: '';
        $best = ',';
        $bestCount = 0;

        foreach ([',', ';', "\t"] as $candidate) {
            $count = substr_count($header, $candidate);

            if ($count > $bestCount) {
                [$best, $bestCount] = [$candidate, $count];
            }
        }

        return $best;
    }

    private function fileError(string $message): ValidationException
    {
        return ValidationException::withMessages(['file' => $message]);
    }
}
