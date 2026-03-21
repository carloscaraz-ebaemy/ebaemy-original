<?php

namespace App\Exceptions;

use Exception;

class InsufficientStockException extends Exception
{
    public function __construct(string $message = 'Stock insuficiente', int $code = 422)
    {
        parent::__construct($message, $code);
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
                'error'   => 'insufficient_stock',
            ], 422);
        }
        return back()->withErrors(['stock' => $this->getMessage()]);
    }
}
