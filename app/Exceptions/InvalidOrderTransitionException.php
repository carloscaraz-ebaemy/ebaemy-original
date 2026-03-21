<?php

namespace App\Exceptions;

use Exception;

class InvalidOrderTransitionException extends Exception
{
    public function __construct(string $message = 'Transición de estado inválida', int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
                'error'   => 'invalid_state_transition',
            ], 422);
        }
        return back()->withErrors(['status' => $this->getMessage()]);
    }
}
