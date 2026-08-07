<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exception thrown when an invalid status transition is attempted for a ticket.
 */
class InvalidTicketStatusTransitionException extends Exception
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct("Ungültiger Statusübergang von '{$from}' zu '{$to}'.");
    }

    /**
     * Laravel automatically calls render() when the exception has this
     * method — no additional registration in bootstrap/app.php needed.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'from' => $this->from,
            'to' => $this->to,
        ], 409);
    }
}
