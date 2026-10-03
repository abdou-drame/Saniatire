<?php

namespace App\Domain\Platform\Payments;

use RuntimeException;

/** Échec d'un appel à DexPay, avec la réponse brute pour le diagnostic. */
class DexPayException extends RuntimeException
{
    /** @param  array<string, mixed>  $response */
    public function __construct(string $message, public readonly array $response = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
