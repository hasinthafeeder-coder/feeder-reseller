<?php

namespace App\Support;

use Feeder\Core\Support\DecimalAmount;
use Throwable;

/**
 * Formats a stored decimal string for display.
 *
 * Missing or unreadable values stay hidden. They are never shown as zero.
 */
final class StoredMoneyDisplay
{
    public static function format(mixed $amount): ?string
    {
        if (is_int($amount)) {
            $amount = (string) $amount;
        }

        if (! is_string($amount)) {
            return null;
        }

        $trimmed = trim($amount);

        if ($trimmed === '' || preg_match('/^-?\d+(\.\d+)?$/', $trimmed) !== 1) {
            return null;
        }

        try {
            return DecimalAmount::formatSigned($trimmed);
        } catch (Throwable) {
            return null;
        }
    }
}
