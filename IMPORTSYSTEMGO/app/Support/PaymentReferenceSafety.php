<?php

namespace App\Support;

final class PaymentReferenceSafety
{
    public static function containsFullCardNumber(string $reference): bool
    {
        return preg_match('/(?<!\d)(?:\d[ -]?){12,18}\d(?!\d)/', $reference) === 1;
    }
}
