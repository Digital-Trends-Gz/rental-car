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

        $messages = [
            'ar' => 'This field must contain letters only and cannot include numbers.',
            'ur' => 'This field must contain letters only and cannot include numbers.',
        ];

        $fail($messages[app()->getLocale()] ?? 'This field must contain letters only and cannot include numbers.');
    }
}
