<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class LettersOnly implements ValidationRule
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

        if (preg_match('/^[\pL][\pL\s\.\-\'"&]*$/u', $value)) {
            return;
        }

        $key = 'validation.letters_only';
        $message = trans($key);

        $fail($message === $key ? 'This field must contain letters only and cannot include numbers.' : $message);
    }
}
