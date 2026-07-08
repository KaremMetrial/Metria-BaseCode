<?php

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    'login_locked' => 'Your account has been temporarily locked due to too many failed login attempts.',
    
    'mfa' => [
        'required' => 'Multi-factor authentication is required to proceed.',
        'invalid_code' => 'The provided multi-factor authentication code is invalid.',
        'enabled' => 'Multi-factor authentication has been enabled.',
        'confirmed' => 'Multi-factor authentication has been confirmed successfully.',
        'disabled' => 'Multi-factor authentication has been disabled.',
        'not_initialized' => 'MFA setup has not been initiated.',
        'invalid_password' => 'Invalid password provided for MFA deactivation.',
    ],

    'social' => [
        'linked' => 'Successfully linked :provider account.',
        'unlinked' => 'Successfully unlinked :provider account.',
        'already_linked' => 'This :provider account is already linked to another user.',
        'conflict' => 'This social account is already linked to another user profile.',
        'cannot_unlink_only' => 'Cannot unlink your only authentication method without setting a password or linking another social account.',
        'provider_disabled' => 'OAuth provider [:provider] is not configured or is currently disabled.',
        'tenant_mismatch' => 'Your account belongs to a different organization.',
    ],

    'governance' => [
        'method_disabled' => 'Authentication method [:method] is currently disabled by system policy.',
    ],

    'recovery' => [
        'sent' => 'If an account exists with that email, a password reset link has been sent.',
        'reset_success' => 'Your password has been reset successfully.',
        'invalid_token' => 'Invalid or expired password reset token.',
    ],

    'session' => [
        'revoked' => 'Session revoked successfully.',
        'all_revoked' => 'All active sessions have been revoked successfully.',
    ],
];
