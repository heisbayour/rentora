<?php

declare(strict_types=1);

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'inactive' => 'Your account has been deactivated. Please contact your property manager.',
    'current_password_mismatch' => 'The provided password does not match your current password.',

    'login' => [
        'title' => 'Log in',
        'heading' => 'Log in to your account',
        'email' => 'Email',
        'password' => 'Password',
        'remember' => 'Remember me',
        'submit' => 'Log in',
        'forgot' => 'Forgot your password?',
    ],
    'forgot' => [
        'title' => 'Forgot password',
        'heading' => 'Reset your password',
        'intro' => 'Enter your email and we will send you a link to choose a new password.',
        'submit' => 'Email reset link',
        'back' => 'Back to log in',
    ],
    'reset' => [
        'title' => 'Choose a new password',
        'heading' => 'Choose a new password',
        'password' => 'New password',
        'confirm' => 'Confirm new password',
        'submit' => 'Reset password',
    ],
    'confirm' => [
        'title' => 'Confirm password',
        'heading' => 'Confirm your password',
        'intro' => 'This is a secure area. Please confirm your password to continue.',
        'submit' => 'Confirm',
    ],
    'two_factor' => [
        'title' => 'Two-factor authentication',
        'heading' => 'Two-factor authentication',
        'intro' => 'Enter the 6-digit code from your authenticator app.',
        'recovery_intro' => 'Enter one of your emergency recovery codes.',
        'code' => 'Authentication code',
        'recovery_code' => 'Recovery code',
        'use_recovery' => 'Use a recovery code',
        'use_code' => 'Use an authentication code',
        'submit' => 'Verify',
    ],
    'invitation' => [
        'title' => 'Set your password',
        'heading' => 'Welcome, :name',
        'intro' => 'Choose a password to activate your account.',
        'password' => 'Password',
        'confirm' => 'Confirm password',
        'staff_hint' => 'At least 8 characters, with upper and lower case letters and a number.',
        'submit' => 'Activate account',
    ],
];
