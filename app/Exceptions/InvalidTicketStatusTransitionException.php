<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exception, die geworfen wird, wenn ein ungültiger Statusübergang für ein Ticket versucht wird.
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
     * Laravel ruft render() automatisch auf, wenn die Exception diese Methode
     * besitzt — keine zusätzliche Registrierung in bootstrap/app.php nötig.
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
