<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** A domain rule was violated (e.g. illegal stage jump). Rendered as HTTP 422. */
class BusinessRuleException extends RuntimeException
{
    /** @param array<string, mixed>|null $details */
    public function __construct(string $message, private readonly ?array $details = null)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'BUSINESS_RULE_VIOLATION',
                'message' => $this->getMessage(),
                'details' => $this->details,
            ],
        ], 422);
    }
}
