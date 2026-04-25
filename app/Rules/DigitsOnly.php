<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DigitsOnly implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        if (preg_match('/^\d+$/', $value)) {
            return;
        }

        $fail('This field must contain numbers only.');
    }
}
