<?php

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'social_email_already_exists' => 'یہ ای میل پہلے سے کسی دوسرے اکاؤنٹ میں رجسٹرڈ ہے۔ براہ کرم اپنی معلومات سے لاگ ان کریں یا کوئی دوسری ای میل استعمال کریں۔',
    'social_email_missing' => 'سوشل لاگ ان فراہم کنندہ سے ای میل حاصل کرنے میں ناکام۔',
    'social_login_failed' => 'سوشل لاگ ان کی تصدیق ناکام ہوگئی۔ براہ کرم دوبارہ کوشش کریں۔',
    'api' => [
        'account_not_found' => 'We could not find an account with this email.',
        'otp_required' => 'The otp field is required.',
        'otp_digits' => 'The otp must be exactly 6 digits.',
        'otp_invalid' => 'The OTP code is invalid.',
        'otp_blocked' => 'OTP verification is blocked for 2 minutes. Please try again later.',
        'success' => 'success',
        'password_reset_sent' => 'If the account exists, we sent a password reset link and OTP to the registered email.',
        'otp_verify_first' => 'Please verify the OTP first.',
        'password_reset_success' => 'Password reset successfully.',
        'switch_mode_employee_success' => 'Switched to employee mode successfully.',
        'switch_mode_owner_success' => 'Switched to owner mode successfully.',
        'switch_mode_forbidden' => 'Only company owners can switch app mode.',
        'switch_mode_branch_required' => 'Please choose the branch you want to work from as an employee.',
        'switch_mode_branch_invalid' => 'The selected branch is invalid or not accessible.',
        'account_types' => [
            'company_owner' => 'Company owner',
            'employee' => 'Employee',
            'super_admin' => 'Super Admin',
            'client' => 'Client',
        ],
    ],
];
