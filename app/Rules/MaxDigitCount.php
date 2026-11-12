<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MaxDigitCount implements ValidationRule
{
    public function __construct(
        private readonly int $maxDigits = 5,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $digitCount = preg_match_all('/\d/u', $value);
        if ($digitCount === false) {
            return;
        }

        if ($digitCount > $this->maxDigits) {
            $fail("The :attribute may not contain more than {$this->maxDigits} digits.");
        }
    }
}
