<?php

namespace App\Services\Tenant\Quota;

/**
 * Respuesta única de cualquier cupo del plan.
 *
 * Existe para que el mensaje que ve el comerciante se escriba UNA vez. Antes
 * de esto, cada controlador que quisiera avisar de un límite se inventaba su
 * propia frase, y cambiar «10» por «20» obligaba a buscarla por todo el
 * repositorio.
 */
class QuotaResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?int $limit,      // null = ilimitado
        public readonly int $current,
        public readonly ?int $remaining,  // null = ilimitado
        public readonly ?string $message = null,
        public readonly bool $included = true,
    ) {}

    public static function unlimited(int $current): self
    {
        return new self(true, null, $current, null);
    }

    public static function notIncluded(string $message): self
    {
        return new self(false, 0, 0, 0, $message, false);
    }

    /** ¿Queda poco? Sirve para el aviso amable antes de que se acabe. */
    public function isNearLimit(int $threshold = 3): bool
    {
        return $this->remaining !== null
            && $this->remaining > 0
            && $this->remaining <= $threshold;
    }

    public function percentUsed(): ?int
    {
        if ($this->limit === null || $this->limit === 0) {
            return null;
        }

        return (int) min(100, round($this->current * 100 / $this->limit));
    }

    /** Forma serializable para el frontend (barras de progreso y avisos). */
    public function toArray(): array
    {
        return [
            'allowed'   => $this->allowed,
            'included'  => $this->included,
            'limit'     => $this->limit,
            'current'   => $this->current,
            'remaining' => $this->remaining,
            'unlimited' => $this->limit === null,
            'percent'   => $this->percentUsed(),
            'near'      => $this->isNearLimit(),
            'message'   => $this->message,
        ];
    }
}
