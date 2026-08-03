# Complete File Guide

This guide describes every tracked project file as of 5e80242. Dependency artifacts are excluded because they are not tracked.

## `.env.example`

Template environment variables for local setup.

## `.github/workflows/ci.yml`

YAML automation or service configuration.

## `.gitignore`

Git ignore rules for generated and local artifacts.

## `ARCHITECTURE.md`

Project documentation or architectural decision record.

## `README.md`

Project documentation or architectural decision record.

## `app/Providers/AppServiceProvider.php`

Application service provider: `AppServiceProvider`.

## `app/Providers/BroadcastServiceProvider.php`

Application service provider: `BroadcastServiceProvider`.

## `app/Providers/DomainEventServiceProvider.php`

Application service provider: `DomainEventServiceProvider`.

## `artisan`

Laravel Artisan command-line entry point.

## `bootstrap/app.php`

PHP bootstrap or configuration source.

## `bootstrap/cache/.gitignore`

Project configuration or operational artifact.

## `bootstrap/providers.php`

PHP bootstrap or configuration source.

## `composer.json`

JSON manifest or package metadata.

## `composer.lock`

Locked dependency versions for reproducible installs.

## `config/auth.php`

PHP bootstrap or configuration source.

## `config/broadcasting.php`

PHP bootstrap or configuration source.

## `config/core.php`

PHP bootstrap or configuration source.

## `config/features.php`

PHP bootstrap or configuration source.

## `config/permission.php`

PHP bootstrap or configuration source.

## `database/database.sqlite`

SQLite development database fixture.

## `database/migrations/0001_01_01_000002_create_cache_table.php`

Database schema migration: 0001_01_01_000002_create_cache_table

## `database/migrations/0001_01_01_000003_create_jobs_table.php`

Database schema migration: 0001_01_01_000003_create_jobs_table

## `database/seeders/DatabaseSeeder.php`

Database seeder: `DatabaseSeeder`.

## `docker-compose.yml`

YAML automation or service configuration.

## `docker/socket-io/Dockerfile`

Project configuration or operational artifact.

## `docker/socket-io/package.json`

JSON manifest or package metadata.

## `docker/socket-io/server.js`

JavaScript runtime source.

## `docs/adr/ADR-001-modular-monolith-domain-driven-design.md`

Project documentation or architectural decision record.

## `docs/adr/ADR-002-multi-tenant-isolation-and-queue-propagation.md`

Project documentation or architectural decision record.

## `docs/adr/ADR-003-two-phase-payment-refund-saga.md`

Project documentation or architectural decision record.

## `docs/adr/ADR-004-outbox-relay-state-machine-concurrency.md`

Project documentation or architectural decision record.

## `docs/adr/ADR-005-provider-agnostic-ai-translation-infrastructure.md`

Project documentation or architectural decision record.

## `docs/security/threat-model.md`

Project documentation or architectural decision record.

## `implementation_plan.md`

Project documentation or architectural decision record.

## `lang/ar/auth.php`

PHP bootstrap or configuration source.

## `lang/ar/governance.php`

PHP bootstrap or configuration source.

## `lang/ar/integrations.php`

PHP bootstrap or configuration source.

## `lang/ar/media.php`

PHP bootstrap or configuration source.

## `lang/ar/payments.php`

PHP bootstrap or configuration source.

## `lang/ar/rbac.php`

PHP bootstrap or configuration source.

## `lang/ar/territories.php`

PHP bootstrap or configuration source.

## `lang/ar/translations.php`

PHP bootstrap or configuration source.

## `lang/ar/validation.php`

PHP bootstrap or configuration source.

## `lang/ar/wallets.php`

PHP bootstrap or configuration source.

## `lang/en/auth.php`

PHP bootstrap or configuration source.

## `lang/en/governance.php`

PHP bootstrap or configuration source.

## `lang/en/integrations.php`

PHP bootstrap or configuration source.

## `lang/en/media.php`

PHP bootstrap or configuration source.

## `lang/en/payments.php`

PHP bootstrap or configuration source.

## `lang/en/rbac.php`

PHP bootstrap or configuration source.

## `lang/en/territories.php`

PHP bootstrap or configuration source.

## `lang/en/translations.php`

PHP bootstrap or configuration source.

## `lang/en/validation.php`

PHP bootstrap or configuration source.

## `lang/en/wallets.php`

PHP bootstrap or configuration source.

## `modules/Auth/Domain/Contracts/AuthStrategyInterface.php`

Domain contract: `AuthStrategyInterface`.

## `modules/Auth/Domain/Contracts/OAuthConfigurationRepositoryInterface.php`

Domain contract: `OAuthConfigurationRepositoryInterface`.

## `modules/Auth/Domain/Events/AccountLockedOut.php`

Domain event: `AccountLockedOut`.

## `modules/Auth/Domain/Events/AllSessionsRevoked.php`

Domain event: `AllSessionsRevoked`.

## `modules/Auth/Domain/Events/AuthMethodBlocked.php`

Domain event: `AuthMethodBlocked`.

## `modules/Auth/Domain/Events/MfaDisabled.php`

Domain event: `MfaDisabled`.

## `modules/Auth/Domain/Events/MfaEnabled.php`

Domain event: `MfaEnabled`.

## `modules/Auth/Domain/Events/MfaVerified.php`

Domain event: `MfaVerified`.

## `modules/Auth/Domain/Events/OtpFailed.php`

Domain event: `OtpFailed`.

## `modules/Auth/Domain/Events/OtpGenerated.php`

Domain event: `OtpGenerated`.

## `modules/Auth/Domain/Events/OtpVerified.php`

Domain event: `OtpVerified`.

## `modules/Auth/Domain/Events/PasswordResetRequested.php`

Domain event: `PasswordResetRequested`.

## `modules/Auth/Domain/Events/PasswordResetSuccessfully.php`

Domain event: `PasswordResetSuccessfully`.

## `modules/Auth/Domain/Events/SocialIdentityLinked.php`

Domain event: `SocialIdentityLinked`.

## `modules/Auth/Domain/Events/SocialIdentityUnlinked.php`

Domain event: `SocialIdentityUnlinked`.

## `modules/Auth/Domain/Events/UserLoggedIn.php`

Domain event: `UserLoggedIn`.

## `modules/Auth/Domain/Events/UserLoggedInByOtp.php`

Domain event: `UserLoggedInByOtp`.

## `modules/Auth/Domain/Events/UserLoggedInByProvider.php`

Domain event: `UserLoggedInByProvider`.

## `modules/Auth/Domain/Events/UserRegistered.php`

Domain event: `UserRegistered`.

## `modules/Auth/Domain/Events/UserRegisteredByOtp.php`

Domain event: `UserRegisteredByOtp`.

## `modules/Auth/Domain/Events/UserSessionRevoked.php`

Domain event: `UserSessionRevoked`.

## `modules/Auth/Domain/Models/FcmDeviceToken.php`

Domain model: `FcmDeviceToken`.

## `modules/Auth/Domain/Models/OtpCode.php`

Domain model: `OtpCode`.

## `modules/Auth/Domain/Models/User.php`

Domain model: `User`.

## `modules/Auth/Domain/Models/UserSession.php`

Domain model: `UserSession`.

## `modules/Auth/Domain/Models/UserSocialIdentity.php`

Domain model: `UserSocialIdentity`.

## `modules/Auth/Infrastructure/Console/PruneExpiredTokens.php`

Console command: `PruneExpiredTokens`.

## `modules/Auth/Infrastructure/Database/Factories/UserFactory.php`

Model factory: `UserFactory`.

## `modules/Auth/Infrastructure/Database/Migrations/0001_01_01_000001_create_users_table.php`

Database schema migration: 0001_01_01_000001_create_users_table

## `modules/Auth/Infrastructure/Database/Migrations/0001_01_01_000004_create_personal_access_tokens_table.php`

Database schema migration: 0001_01_01_000004_create_personal_access_tokens_table

## `modules/Auth/Infrastructure/Database/Migrations/2025_01_01_000050_create_otps_table.php`

Database schema migration: 2025_01_01_000050_create_otps_table

## `modules/Auth/Infrastructure/Database/Migrations/2025_01_01_000052_create_fcm_device_tokens_table.php`

Database schema migration: 2025_01_01_000052_create_fcm_device_tokens_table

## `modules/Auth/Infrastructure/Database/Migrations/2025_01_01_000053_create_user_sessions_table.php`

Database schema migration: 2025_01_01_000053_create_user_sessions_table

## `modules/Auth/Infrastructure/Database/Migrations/2025_01_01_000054_create_user_social_identities_table.php`

Database schema migration: 2025_01_01_000054_create_user_social_identities_table

## `modules/Auth/Infrastructure/Database/Migrations/2025_01_01_000056_add_two_factor_columns_to_users_table.php`

Database schema migration: 2025_01_01_000056_add_two_factor_columns_to_users_table

## `modules/Auth/Infrastructure/Database/Migrations/2025_01_01_000062_add_tenant_email_index_to_users_table.php`

Database schema migration: 2025_01_01_000062_add_tenant_email_index_to_users_table

## `modules/Auth/Infrastructure/Listeners/AuditSecurityEvent.php`

Event listener: `AuditSecurityEvent`.

## `modules/Auth/Infrastructure/Listeners/NotifyPasswordChanged.php`

Event listener: `NotifyPasswordChanged`.

## `modules/Auth/Infrastructure/Listeners/NotifySocialAccountLinked.php`

Event listener: `NotifySocialAccountLinked`.

## `modules/Auth/Infrastructure/Listeners/ProvisionUserDefaults.php`

Event listener: `ProvisionUserDefaults`.

## `modules/Auth/Infrastructure/Listeners/SendLoginAlert.php`

Event listener: `SendLoginAlert`.

## `modules/Auth/Infrastructure/Listeners/SendOtpNotification.php`

Event listener: `SendOtpNotification`.

## `modules/Auth/Infrastructure/Notifications/LoginAlertNotification.php`

Infrastructure component: `LoginAlertNotification`.

## `modules/Auth/Infrastructure/Notifications/OtpNotification.php`

Infrastructure component: `OtpNotification`.

## `modules/Auth/Infrastructure/Notifications/WelcomeNotification.php`

Infrastructure component: `WelcomeNotification`.

## `modules/Auth/Infrastructure/Pipelines/AuthContext.php`

Infrastructure component: `AuthContext`.

## `modules/Auth/Infrastructure/Pipelines/AuthPipeline.php`

Infrastructure component: `AuthPipeline`.

## `modules/Auth/Infrastructure/Pipelines/CheckAccountStatusPipe.php`

Infrastructure component: `CheckAccountStatusPipe`.

## `modules/Auth/Infrastructure/Pipelines/EnforceMfaPipe.php`

Infrastructure component: `EnforceMfaPipe`.

## `modules/Auth/Infrastructure/Pipelines/IssueTokenPipe.php`

Infrastructure component: `IssueTokenPipe`.

## `modules/Auth/Infrastructure/Providers/AuthServiceProvider.php`

Laravel service provider: `AuthServiceProvider`.

## `modules/Auth/Infrastructure/Services/AuthMethodGovernanceService.php`

Infrastructure service: `AuthMethodGovernanceService`.

## `modules/Auth/Infrastructure/Services/DynamicSocialiteConfigService.php`

Infrastructure service: `DynamicSocialiteConfigService`.

## `modules/Auth/Infrastructure/Services/IssueApiToken.php`

Infrastructure service: `IssueApiToken`.

## `modules/Auth/Infrastructure/Services/LoginWithOtp.php`

Infrastructure service: `LoginWithOtp`.

## `modules/Auth/Infrastructure/Services/MfaService.php`

Infrastructure service: `MfaService`.

## `modules/Auth/Infrastructure/Services/PasswordResetService.php`

Infrastructure service: `PasswordResetService`.

## `modules/Auth/Infrastructure/Services/RegisterUser.php`

Infrastructure service: `RegisterUser`.

## `modules/Auth/Infrastructure/Services/RegisterWithOtp.php`

Infrastructure service: `RegisterWithOtp`.

## `modules/Auth/Infrastructure/Services/SendOtp.php`

Infrastructure service: `SendOtp`.

## `modules/Auth/Infrastructure/Services/SendPushToUser.php`

Infrastructure service: `SendPushToUser`.

## `modules/Auth/Infrastructure/Services/SocialIdentityService.php`

Infrastructure service: `SocialIdentityService`.

## `modules/Auth/Infrastructure/Services/VerifyOtp.php`

Infrastructure service: `VerifyOtp`.

## `modules/Auth/Infrastructure/Strategies/OtpAuthStrategy.php`

Infrastructure component: `OtpAuthStrategy`.

## `modules/Auth/Infrastructure/Strategies/PasswordAuthStrategy.php`

Infrastructure component: `PasswordAuthStrategy`.

## `modules/Auth/Infrastructure/Strategies/SocialProviderStrategy.php`

Infrastructure component: `SocialProviderStrategy`.

## `modules/Auth/Infrastructure/config/auth_features.php`

PHP bootstrap or configuration source.

## `modules/Auth/Presentation/Http/Controllers/Api/V1/AuthController.php`

HTTP controller: `AuthController`.

## `modules/Auth/Presentation/Http/Controllers/Api/V1/OtpAuthController.php`

HTTP controller: `OtpAuthController`.

## `modules/Auth/Presentation/Http/Controllers/Api/V1/SocialAuthController.php`

HTTP controller: `SocialAuthController`.

## `modules/Auth/Presentation/Http/Requests/ConfirmMfaRequest.php`

HTTP request validator: `ConfirmMfaRequest`.

## `modules/Auth/Presentation/Http/Requests/DisableMfaRequest.php`

HTTP request validator: `DisableMfaRequest`.

## `modules/Auth/Presentation/Http/Requests/ForgotPasswordRequest.php`

HTTP request validator: `ForgotPasswordRequest`.

## `modules/Auth/Presentation/Http/Requests/LoginRequest.php`

HTTP request validator: `LoginRequest`.

## `modules/Auth/Presentation/Http/Requests/OtpLoginRequest.php`

HTTP request validator: `OtpLoginRequest`.

## `modules/Auth/Presentation/Http/Requests/OtpRegisterRequest.php`

HTTP request validator: `OtpRegisterRequest`.

## `modules/Auth/Presentation/Http/Requests/RegisterRequest.php`

HTTP request validator: `RegisterRequest`.

## `modules/Auth/Presentation/Http/Requests/ResetPasswordRequest.php`

HTTP request validator: `ResetPasswordRequest`.

## `modules/Auth/Presentation/Http/Requests/SendOtpRequest.php`

HTTP request validator: `SendOtpRequest`.

## `modules/Auth/Presentation/Http/Requests/UpdateFcmTokenRequest.php`

HTTP request validator: `UpdateFcmTokenRequest`.

## `modules/Auth/Presentation/Http/Resources/UserResource.php`

API resource: `UserResource`.

## `modules/Auth/Presentation/Policies/FcmDeviceTokenPolicy.php`

Authorization policy: `FcmDeviceTokenPolicy`.

## `modules/Auth/Presentation/Policies/UserPolicy.php`

Authorization policy: `UserPolicy`.

## `modules/Auth/Presentation/Policies/UserSessionPolicy.php`

Authorization policy: `UserSessionPolicy`.

## `modules/Auth/Presentation/Policies/UserSocialIdentityPolicy.php`

Authorization policy: `UserSocialIdentityPolicy`.

## `modules/Auth/Presentation/routes/api.php`

PHP bootstrap or configuration source.

## `modules/Currency/Application/Services/CurrencyConversionService.php`

PHP class: `CurrencyConversionService`.

## `modules/Currency/Application/Services/CurrencyFormatter.php`

PHP class: `CurrencyFormatter`.

## `modules/Currency/Domain/Contracts/ExchangeRateProviderInterface.php`

Domain contract: `ExchangeRateProviderInterface`.

## `modules/Currency/Domain/Models/Currency.php`

Domain model: `Currency`.

## `modules/Currency/Domain/Models/CurrencyExchangeRate.php`

Domain model: `CurrencyExchangeRate`.

## `modules/Currency/Domain/Repositories/ExchangeRateRepositoryInterface.php`

PHP interface: `ExchangeRateRepositoryInterface`.

## `modules/Currency/Infrastructure/Console/Commands/SyncExchangeRates.php`

Console command: `SyncExchangeRates`.

## `modules/Currency/Infrastructure/Database/Migrations/2025_01_01_000019_create_currencies_table.php`

Database schema migration: 2025_01_01_000019_create_currencies_table

## `modules/Currency/Infrastructure/Database/Migrations/2025_01_01_000041_create_currency_exchange_rates_table.php`

Database schema migration: 2025_01_01_000041_create_currency_exchange_rates_table

## `modules/Currency/Infrastructure/Database/Migrations/2025_01_01_000042_create_currency_exchange_rate_sync_logs_table.php`

Database schema migration: 2025_01_01_000042_create_currency_exchange_rate_sync_logs_table

## `modules/Currency/Infrastructure/Persistence/Repositories/ExchangeRateRepository.php`

Infrastructure component: `ExchangeRateRepository`.

## `modules/Currency/Infrastructure/Providers/CurrencyExchangeApiProvider.php`

Laravel service provider: `CurrencyExchangeApiProvider`.

## `modules/Currency/Infrastructure/Providers/CurrencyServiceProvider.php`

Laravel service provider: `CurrencyServiceProvider`.

## `modules/Currency/Infrastructure/Providers/ExchangeRateProviderChain.php`

Laravel service provider: `ExchangeRateProviderChain`.

## `modules/Currency/Infrastructure/Providers/MockExchangeRateProvider.php`

Laravel service provider: `MockExchangeRateProvider`.

## `modules/Currency/Infrastructure/Resources/lang/ar/currency.php`

PHP bootstrap or configuration source.

## `modules/Currency/Infrastructure/Resources/lang/en/currency.php`

PHP bootstrap or configuration source.

## `modules/Currency/Infrastructure/Services/CurrencyRegistryResolverImpl.php`

Infrastructure service: `CurrencyRegistryResolverImpl`.

## `modules/Currency/Infrastructure/Services/ExchangeRateResolver.php`

Infrastructure service: `ExchangeRateResolver`.

## `modules/Currency/Infrastructure/Services/ExchangeRateService.php`

Infrastructure service: `ExchangeRateService`.

## `modules/Currency/Infrastructure/config/currencies.php`

PHP bootstrap or configuration source.

## `modules/Currency/Presentation/Policies/CurrencyExchangeRatePolicy.php`

Authorization policy: `CurrencyExchangeRatePolicy`.

## `modules/Currency/Presentation/Policies/CurrencyPolicy.php`

Authorization policy: `CurrencyPolicy`.

## `modules/Governance/Domain/Enums/ApprovalStatus.php`

PHP enum: `ApprovalStatus`.

## `modules/Governance/Domain/Models/ApprovalRequest.php`

Domain model: `ApprovalRequest`.

## `modules/Governance/Domain/Models/AuditLog.php`

Domain model: `AuditLog`.

## `modules/Governance/Domain/Models/FeatureFlag.php`

Domain model: `FeatureFlag`.

## `modules/Governance/Domain/Models/Setting.php`

Domain model: `Setting`.

## `modules/Governance/Infrastructure/Console/Commands/PruneGovernanceData.php`

Console command: `PruneGovernanceData`.

## `modules/Governance/Infrastructure/Database/Migrations/2025_01_01_000010_create_settings_table.php`

Database schema migration: 2025_01_01_000010_create_settings_table

## `modules/Governance/Infrastructure/Database/Migrations/2025_01_01_000011_create_feature_flags_table.php`

Database schema migration: 2025_01_01_000011_create_feature_flags_table

## `modules/Governance/Infrastructure/Database/Migrations/2025_01_01_000012_create_audit_logs_table.php`

Database schema migration: 2025_01_01_000012_create_audit_logs_table

## `modules/Governance/Infrastructure/Database/Migrations/2025_01_01_000013_create_approval_requests_table.php`

Database schema migration: 2025_01_01_000013_create_approval_requests_table

## `modules/Governance/Infrastructure/Observers/AuditableObserver.php`

Infrastructure component: `AuditableObserver`.

## `modules/Governance/Infrastructure/Providers/GovernanceServiceProvider.php`

Laravel service provider: `GovernanceServiceProvider`.

## `modules/Governance/Infrastructure/Services/ApprovalService.php`

Infrastructure service: `ApprovalService`.

## `modules/Governance/Infrastructure/Services/AuditLogger.php`

Infrastructure service: `AuditLogger`.

## `modules/Governance/Infrastructure/Services/FeatureFlagService.php`

Infrastructure service: `FeatureFlagService`.

## `modules/Governance/Infrastructure/Services/SettingsService.php`

Infrastructure service: `SettingsService`.

## `modules/Governance/Infrastructure/Traits/Auditable.php`

Infrastructure component: `Auditable`.

## `modules/Governance/Infrastructure/config/governance.php`

PHP bootstrap or configuration source.

## `modules/Governance/Presentation/Http/Controllers/Api/V1/ApprovalController.php`

HTTP controller: `ApprovalController`.

## `modules/Governance/Presentation/Http/Controllers/Api/V1/AuditLogController.php`

HTTP controller: `AuditLogController`.

## `modules/Governance/Presentation/Http/Controllers/Api/V1/FeatureFlagController.php`

HTTP controller: `FeatureFlagController`.

## `modules/Governance/Presentation/Http/Controllers/Api/V1/SettingsController.php`

HTTP controller: `SettingsController`.

## `modules/Governance/Presentation/Http/Requests/UpdateSettingRequest.php`

HTTP request validator: `UpdateSettingRequest`.

## `modules/Governance/Presentation/Http/Resources/ApprovalRequestResource.php`

API resource: `ApprovalRequestResource`.

## `modules/Governance/Presentation/Http/Resources/AuditLogResource.php`

API resource: `AuditLogResource`.

## `modules/Governance/Presentation/Policies/ApprovalRequestPolicy.php`

Authorization policy: `ApprovalRequestPolicy`.

## `modules/Governance/Presentation/Policies/AuditLogPolicy.php`

Authorization policy: `AuditLogPolicy`.

## `modules/Governance/Presentation/Policies/FeatureFlagPolicy.php`

Authorization policy: `FeatureFlagPolicy`.

## `modules/Governance/Presentation/Policies/SettingPolicy.php`

Authorization policy: `SettingPolicy`.

## `modules/Governance/Presentation/routes/api.php`

PHP bootstrap or configuration source.

## `modules/Integration/Domain/Contracts/SmsProvider.php`

Domain contract: `SmsProvider`.

## `modules/Integration/Domain/Models/OAuthProvider.php`

Domain model: `OAuthProvider`.

## `modules/Integration/Infrastructure/Database/Migrations/2025_01_01_000055_create_oauth_providers_table.php`

Database schema migration: 2025_01_01_000055_create_oauth_providers_table

## `modules/Integration/Infrastructure/Providers/IntegrationServiceProvider.php`

Laravel service provider: `IntegrationServiceProvider`.

## `modules/Integration/Infrastructure/Push/FcmPushProvider.php`

Infrastructure component: `FcmPushProvider`.

## `modules/Integration/Infrastructure/Repositories/DatabaseOAuthConfigurationRepository.php`

Repository implementation: `DatabaseOAuthConfigurationRepository`.

## `modules/Integration/Infrastructure/Sms/LogDriver.php`

Infrastructure component: `LogDriver`.

## `modules/Integration/Infrastructure/Sms/SmsManager.php`

Infrastructure component: `SmsManager`.

## `modules/Integration/Infrastructure/Sms/TwilioDriver.php`

Infrastructure component: `TwilioDriver`.

## `modules/Integration/Infrastructure/Sms/VonageDriver.php`

Infrastructure component: `VonageDriver`.

## `modules/Integration/Infrastructure/Support/ApiClient.php`

Infrastructure component: `ApiClient`.

## `modules/Integration/Infrastructure/Support/CircuitBreaker.php`

Infrastructure component: `CircuitBreaker`.

## `modules/Integration/Infrastructure/config/integrations.php`

PHP bootstrap or configuration source.

## `modules/Integration/Presentation/Http/Controllers/Api/V1/OAuthProviderController.php`

HTTP controller: `OAuthProviderController`.

## `modules/Integration/Presentation/Http/Requests/UpdateOAuthProviderRequest.php`

HTTP request validator: `UpdateOAuthProviderRequest`.

## `modules/Integration/Presentation/Policies/OAuthProviderPolicy.php`

Authorization policy: `OAuthProviderPolicy`.

## `modules/Integration/Presentation/routes/api.php`

PHP bootstrap or configuration source.

## `modules/Media/Domain/Contracts/ContentModerator.php`

Domain contract: `ContentModerator`.

## `modules/Media/Domain/Contracts/VirusScanner.php`

Domain contract: `VirusScanner`.

## `modules/Media/Domain/DTOs/ModerationResult.php`

PHP class: `ModerationResult`.

## `modules/Media/Domain/DTOs/VirusScanResult.php`

PHP class: `VirusScanResult`.

## `modules/Media/Domain/Enums/MediaStatus.php`

PHP enum: `MediaStatus`.

## `modules/Media/Domain/Enums/MediaType.php`

PHP enum: `MediaType`.

## `modules/Media/Domain/Enums/MediaVariantType.php`

PHP enum: `MediaVariantType`.

## `modules/Media/Domain/Events/MediaActivated.php`

Domain event: `MediaActivated`.

## `modules/Media/Domain/Events/MediaQuarantined.php`

Domain event: `MediaQuarantined`.

## `modules/Media/Domain/Events/MediaUploadInitiated.php`

Domain event: `MediaUploadInitiated`.

## `modules/Media/Domain/Events/MediaUploaded.php`

Domain event: `MediaUploaded`.

## `modules/Media/Domain/Events/MediaVerified.php`

Domain event: `MediaVerified`.

## `modules/Media/Domain/Models/Media.php`

Domain model: `Media`.

## `modules/Media/Domain/Models/MediaBlob.php`

Domain model: `MediaBlob`.

## `modules/Media/Domain/Models/MediaVariant.php`

Domain model: `MediaVariant`.

## `modules/Media/Infrastructure/Database/Migrations/2025_01_01_000060_create_media_blobs_table.php`

Database schema migration: 2025_01_01_000060_create_media_blobs_table

## `modules/Media/Infrastructure/Database/Migrations/2025_01_01_000061_create_media_attachments_table.php`

Database schema migration: 2025_01_01_000061_create_media_attachments_table

## `modules/Media/Infrastructure/Database/Migrations/2025_01_01_000061_fix_media_blobs_null_tenant_unique_index.php`

Database schema migration: 2025_01_01_000061_fix_media_blobs_null_tenant_unique_index

## `modules/Media/Infrastructure/Database/Migrations/2025_01_01_000062_create_media_variants_table.php`

Database schema migration: 2025_01_01_000062_create_media_variants_table

## `modules/Media/Infrastructure/Jobs/ProcessMediaVariants.php`

Infrastructure component: `ProcessMediaVariants`.

## `modules/Media/Infrastructure/Jobs/VerifyMediaUpload.php`

Infrastructure component: `VerifyMediaUpload`.

## `modules/Media/Infrastructure/Providers/MediaServiceProvider.php`

Laravel service provider: `MediaServiceProvider`.

## `modules/Media/Infrastructure/Services/ClamAvVirusScanner.php`

Infrastructure service: `ClamAvVirusScanner`.

## `modules/Media/Infrastructure/Services/MediaDownloadService.php`

Infrastructure service: `MediaDownloadService`.

## `modules/Media/Infrastructure/Services/MediaProcessingService.php`

Infrastructure service: `MediaProcessingService`.

## `modules/Media/Infrastructure/Services/MediaStateMachine.php`

Infrastructure service: `MediaStateMachine`.

## `modules/Media/Infrastructure/Services/MediaUploadService.php`

Infrastructure service: `MediaUploadService`.

## `modules/Media/Infrastructure/Services/MediaVerificationService.php`

Infrastructure service: `MediaVerificationService`.

## `modules/Media/Infrastructure/Services/RekognitionModerator.php`

Infrastructure service: `RekognitionModerator`.

## `modules/Media/Infrastructure/config/media.php`

PHP bootstrap or configuration source.

## `modules/Media/Presentation/Http/Controllers/Api/V1/MediaController.php`

HTTP controller: `MediaController`.

## `modules/Media/Presentation/Http/Requests/ConfirmUploadRequest.php`

HTTP request validator: `ConfirmUploadRequest`.

## `modules/Media/Presentation/Http/Requests/GeneratePresignedUrlRequest.php`

HTTP request validator: `GeneratePresignedUrlRequest`.

## `modules/Media/Presentation/Http/Resources/MediaResource.php`

API resource: `MediaResource`.

## `modules/Media/Presentation/Policies/MediaPolicy.php`

Authorization policy: `MediaPolicy`.

## `modules/Media/Presentation/routes/api.php`

PHP bootstrap or configuration source.

## `modules/Payment/Domain/Contracts/PaymentGateway.php`

Domain contract: `PaymentGateway`.

## `modules/Payment/Domain/DTOs/PaymentResult.php`

PHP class: `PaymentResult`.

## `modules/Payment/Domain/DTOs/WebhookResult.php`

PHP class: `WebhookResult`.

## `modules/Payment/Domain/Enums/PaymentStatus.php`

PHP enum: `PaymentStatus`.

## `modules/Payment/Domain/Events/PaymentFailed.php`

Domain event: `PaymentFailed`.

## `modules/Payment/Domain/Events/PaymentRefundFailed.php`

Domain event: `PaymentRefundFailed`.

## `modules/Payment/Domain/Events/PaymentRefunded.php`

Domain event: `PaymentRefunded`.

## `modules/Payment/Domain/Events/PaymentSucceeded.php`

Domain event: `PaymentSucceeded`.

## `modules/Payment/Domain/Models/Payment.php`

Domain model: `Payment`.

## `modules/Payment/Infrastructure/Database/Migrations/2025_01_01_000021_create_payments_table.php`

Database schema migration: 2025_01_01_000021_create_payments_table

## `modules/Payment/Infrastructure/Gateways/FawryGateway.php`

Infrastructure component: `FawryGateway`.

## `modules/Payment/Infrastructure/Gateways/PaymobGateway.php`

Infrastructure component: `PaymobGateway`.

## `modules/Payment/Infrastructure/Gateways/PaytabsGateway.php`

Infrastructure component: `PaytabsGateway`.

## `modules/Payment/Infrastructure/Gateways/StripeGateway.php`

Infrastructure component: `StripeGateway`.

## `modules/Payment/Infrastructure/Providers/PaymentServiceProvider.php`

Laravel service provider: `PaymentServiceProvider`.

## `modules/Payment/Infrastructure/Services/ApproveRefundHandler.php`

Infrastructure service: `ApproveRefundHandler`.

## `modules/Payment/Infrastructure/Services/PaymentManager.php`

Infrastructure service: `PaymentManager`.

## `modules/Payment/Infrastructure/Services/PaymentService.php`

Infrastructure service: `PaymentService`.

## `modules/Payment/Infrastructure/config/payments.php`

PHP bootstrap or configuration source.

## `modules/Payment/Presentation/Http/Controllers/Api/V1/PaymentController.php`

HTTP controller: `PaymentController`.

## `modules/Payment/Presentation/Http/Controllers/Api/V1/PaymentWebhookController.php`

HTTP controller: `PaymentWebhookController`.

## `modules/Payment/Presentation/Http/Requests/CreatePaymentRequest.php`

HTTP request validator: `CreatePaymentRequest`.

## `modules/Payment/Presentation/Http/Requests/RefundPaymentRequest.php`

HTTP request validator: `RefundPaymentRequest`.

## `modules/Payment/Presentation/Http/Resources/PaymentResource.php`

API resource: `PaymentResource`.

## `modules/Payment/Presentation/Policies/PaymentPolicy.php`

Authorization policy: `PaymentPolicy`.

## `modules/Payment/Presentation/routes/api.php`

PHP bootstrap or configuration source.

## `modules/RBAC/Application/DTOs/CreateRoleDTO.php`

PHP class: `CreateRoleDTO`.

## `modules/RBAC/Application/DTOs/UpdateRoleDTO.php`

PHP class: `UpdateRoleDTO`.

## `modules/RBAC/Application/Exceptions/PrivilegeEscalationException.php`

PHP class: `PrivilegeEscalationException`.

## `modules/RBAC/Application/Exceptions/RoleNotEditableException.php`

PHP class: `RoleNotEditableException`.

## `modules/RBAC/Domain/Contracts/PermissionRepositoryInterface.php`

Domain contract: `PermissionRepositoryInterface`.

## `modules/RBAC/Domain/Contracts/RoleRepositoryInterface.php`

Domain contract: `RoleRepositoryInterface`.

## `modules/RBAC/Domain/Events/RoleCreated.php`

Domain event: `RoleCreated`.

## `modules/RBAC/Domain/Events/RoleDeleted.php`

Domain event: `RoleDeleted`.

## `modules/RBAC/Domain/Events/RolePermissionsUpdated.php`

Domain event: `RolePermissionsUpdated`.

## `modules/RBAC/Domain/Events/UserRolesUpdated.php`

Domain event: `UserRolesUpdated`.

## `modules/RBAC/Domain/Models/Role.php`

Domain model: `Role`.

## `modules/RBAC/Domain/Models/RoleMetadata.php`

Domain model: `RoleMetadata`.

## `modules/RBAC/Infrastructure/Actions/CreateRoleAction.php`

Infrastructure component: `CreateRoleAction`.

## `modules/RBAC/Infrastructure/Actions/DeleteRoleAction.php`

Infrastructure component: `DeleteRoleAction`.

## `modules/RBAC/Infrastructure/Actions/SyncRolePermissionsAction.php`

Infrastructure component: `SyncRolePermissionsAction`.

## `modules/RBAC/Infrastructure/Actions/SyncUserRolesAction.php`

Infrastructure component: `SyncUserRolesAction`.

## `modules/RBAC/Infrastructure/Actions/UpdateRoleAction.php`

Infrastructure component: `UpdateRoleAction`.

## `modules/RBAC/Infrastructure/Console/Commands/SyncPermissionsCommand.php`

Console command: `SyncPermissionsCommand`.

## `modules/RBAC/Infrastructure/Database/Migrations/0001_01_01_000005_create_permission_tables.php`

Database schema migration: 0001_01_01_000005_create_permission_tables

## `modules/RBAC/Infrastructure/Database/Migrations/2026_07_13_084009_create_role_metadata_table.php`

Database schema migration: 2026_07_13_084009_create_role_metadata_table

## `modules/RBAC/Infrastructure/Database/Migrations/2026_07_13_085423_make_role_metadata_translatable.php`

Database schema migration: 2026_07_13_085423_make_role_metadata_translatable

## `modules/RBAC/Infrastructure/Database/Migrations/2026_07_13_090054_add_priority_to_role_metadata_table.php`

Database schema migration: 2026_07_13_090054_add_priority_to_role_metadata_table

## `modules/RBAC/Infrastructure/Database/Seeders/RolesAndPermissionsSeeder.php`

Database seeder: `RolesAndPermissionsSeeder`.

## `modules/RBAC/Infrastructure/Listeners/AuditRbacEvent.php`

Event listener: `AuditRbacEvent`.

## `modules/RBAC/Infrastructure/Listeners/ClearRbacCache.php`

Event listener: `ClearRbacCache`.

## `modules/RBAC/Infrastructure/Providers/RbacAuthServiceProvider.php`

Laravel service provider: `RbacAuthServiceProvider`.

## `modules/RBAC/Infrastructure/Providers/RbacServiceProvider.php`

Laravel service provider: `RbacServiceProvider`.

## `modules/RBAC/Infrastructure/Repositories/PermissionRepository.php`

Repository implementation: `PermissionRepository`.

## `modules/RBAC/Infrastructure/Repositories/RoleRepository.php`

Repository implementation: `RoleRepository`.

## `modules/RBAC/Infrastructure/Scopes/SystemAwareTenantScope.php`

Infrastructure component: `SystemAwareTenantScope`.

## `modules/RBAC/Infrastructure/Support/AuthorizationCache.php`

Infrastructure component: `AuthorizationCache`.

## `modules/RBAC/Infrastructure/Support/PermissionRegistry.php`

Infrastructure component: `PermissionRegistry`.

## `modules/RBAC/Presentation/Http/Controllers/Api/V1/EffectivePermissionController.php`

HTTP controller: `EffectivePermissionController`.

## `modules/RBAC/Presentation/Http/Controllers/Api/V1/PermissionController.php`

HTTP controller: `PermissionController`.

## `modules/RBAC/Presentation/Http/Controllers/Api/V1/RoleController.php`

HTTP controller: `RoleController`.

## `modules/RBAC/Presentation/Http/Controllers/Api/V1/RolePermissionController.php`

HTTP controller: `RolePermissionController`.

## `modules/RBAC/Presentation/Http/Controllers/Api/V1/UserRoleController.php`

HTTP controller: `UserRoleController`.

## `modules/RBAC/Presentation/Http/Requests/StoreRoleRequest.php`

HTTP request validator: `StoreRoleRequest`.

## `modules/RBAC/Presentation/Http/Requests/UpdateRoleRequest.php`

HTTP request validator: `UpdateRoleRequest`.

## `modules/RBAC/Presentation/Http/Resources/RoleResource.php`

API resource: `RoleResource`.

## `modules/RBAC/Presentation/Policies/RolePolicy.php`

Authorization policy: `RolePolicy`.

## `modules/RBAC/Presentation/routes/api.php`

PHP bootstrap or configuration source.

## `modules/Shared/Application/Abstracts/DataTransferObject.php`

PHP class: `DataTransferObject`.

## `modules/Shared/Application/Exceptions/ApiException.php`

PHP class: `ApiException`.

## `modules/Shared/Application/Exceptions/DomainException.php`

PHP class: `DomainException`.

## `modules/Shared/Application/Exceptions/IntegrationException.php`

PHP class: `IntegrationException`.

## `modules/Shared/Application/Exceptions/PaymentException.php`

PHP class: `PaymentException`.

## `modules/Shared/Application/Support/EnumRegistry.php`

PHP class: `EnumRegistry`.

## `modules/Shared/Domain/Contracts/CurrencyRegistryResolver.php`

Domain contract: `CurrencyRegistryResolver`.

## `modules/Shared/Domain/Contracts/RepositoryInterface.php`

Domain contract: `RepositoryInterface`.

## `modules/Shared/Domain/Events/BroadcastableEvent.php`

Domain event: `BroadcastableEvent`.

## `modules/Shared/Domain/Events/DomainEvent.php`

Domain event: `DomainEvent`.

## `modules/Shared/Domain/Events/StoredInOutbox.php`

Domain event: `StoredInOutbox`.

## `modules/Shared/Domain/Specifications/QueryFilter.php`

PHP class: `QueryFilter`.

## `modules/Shared/Domain/Support/Money.php`

PHP bootstrap or configuration source.

## `modules/Shared/Domain/Support/Result.php`

PHP bootstrap or configuration source.

## `modules/Shared/Infrastructure/Broadcasting/DualBroadcaster.php`

Infrastructure component: `DualBroadcaster`.

## `modules/Shared/Infrastructure/Database/Migrations/0001_01_01_000000_create_tenants_table.php`

Database schema migration: 0001_01_01_000000_create_tenants_table

## `modules/Shared/Infrastructure/Database/Migrations/2025_01_01_000014_create_outbox_messages_table.php`

Database schema migration: 2025_01_01_000014_create_outbox_messages_table

## `modules/Shared/Infrastructure/Database/Migrations/2025_01_01_000015_create_idempotency_keys_table.php`

Database schema migration: 2025_01_01_000015_create_idempotency_keys_table

## `modules/Shared/Infrastructure/Database/Migrations/2026_07_14_000000_add_reserved_columns_to_outbox_messages_table.php`

Database schema migration: 2026_07_14_000000_add_reserved_columns_to_outbox_messages_table

## `modules/Shared/Infrastructure/Database/Migrations/2026_07_14_000001_create_translation_jobs_table.php`

Database schema migration: 2026_07_14_000001_create_translation_jobs_table

## `modules/Shared/Infrastructure/Events/EventBus.php`

Infrastructure component: `EventBus`.

## `modules/Shared/Infrastructure/Localization/LangPathRegistry.php`

Infrastructure component: `LangPathRegistry`.

## `modules/Shared/Infrastructure/Notifications/Channels/FcmChannel.php`

Infrastructure component: `FcmChannel`.

## `modules/Shared/Infrastructure/Notifications/Channels/SmsChannel.php`

Infrastructure component: `SmsChannel`.

## `modules/Shared/Infrastructure/Persistence/BaseRepository.php`

Infrastructure component: `BaseRepository`.

## `modules/Shared/Infrastructure/Persistence/Models/IdempotencyKey.php`

Infrastructure component: `IdempotencyKey`.

## `modules/Shared/Infrastructure/Persistence/Models/OutboxMessage.php`

Infrastructure component: `OutboxMessage`.

## `modules/Shared/Infrastructure/Providers/CoreServiceProvider.php`

Laravel service provider: `CoreServiceProvider`.

## `modules/Shared/Infrastructure/Providers/QueueTenantProvider.php`

Laravel service provider: `QueueTenantProvider`.

## `modules/Shared/Infrastructure/Providers/SharedServiceProvider.php`

Laravel service provider: `SharedServiceProvider`.

## `modules/Shared/Infrastructure/Queue/Middleware/RestoreTenantContext.php`

Infrastructure component: `RestoreTenantContext`.

## `modules/Shared/Infrastructure/Resources/lang/ar/api.php`

PHP bootstrap or configuration source.

## `modules/Shared/Infrastructure/Resources/lang/ar/core.php`

PHP bootstrap or configuration source.

## `modules/Shared/Infrastructure/Resources/lang/en/api.php`

PHP bootstrap or configuration source.

## `modules/Shared/Infrastructure/Resources/lang/en/core.php`

PHP bootstrap or configuration source.

## `modules/Shared/Infrastructure/Tenancy/TenantManager.php`

Infrastructure component: `TenantManager`.

## `modules/Shared/Infrastructure/Tenancy/TenantScope.php`

Infrastructure component: `TenantScope`.

## `modules/Shared/Infrastructure/Traits/BelongsToTenant.php`

Infrastructure component: `BelongsToTenant`.

## `modules/Shared/Infrastructure/Traits/Filterable.php`

Infrastructure component: `Filterable`.

## `modules/Shared/Infrastructure/Traits/HasTranslations.php`

Infrastructure component: `HasTranslations`.

## `modules/Shared/Infrastructure/Traits/HasUuid.php`

Infrastructure component: `HasUuid`.

## `modules/Shared/Infrastructure/Translation/Contracts/PromptInterface.php`

Infrastructure component: `PromptInterface`.

## `modules/Shared/Infrastructure/Translation/Contracts/TranslationProviderInterface.php`

Infrastructure component: `TranslationProviderInterface`.

## `modules/Shared/Infrastructure/Translation/DTOs/ProviderHealth.php`

Infrastructure component: `ProviderHealth`.

## `modules/Shared/Infrastructure/Translation/Enums/ProviderState.php`

Infrastructure component: `ProviderState`.

## `modules/Shared/Infrastructure/Translation/Events/TranslationCompleted.php`

Infrastructure component: `TranslationCompleted`.

## `modules/Shared/Infrastructure/Translation/Events/TranslationFailed.php`

Infrastructure component: `TranslationFailed`.

## `modules/Shared/Infrastructure/Translation/Events/TranslationRequested.php`

Infrastructure component: `TranslationRequested`.

## `modules/Shared/Infrastructure/Translation/Exceptions/InvalidTranslationResponseException.php`

Infrastructure component: `InvalidTranslationResponseException`.

## `modules/Shared/Infrastructure/Translation/Exceptions/ProviderUnavailableException.php`

Infrastructure component: `ProviderUnavailableException`.

## `modules/Shared/Infrastructure/Translation/Exceptions/RateLimitedException.php`

Infrastructure component: `RateLimitedException`.

## `modules/Shared/Infrastructure/Translation/Exceptions/TranslationException.php`

Infrastructure component: `TranslationException`.

## `modules/Shared/Infrastructure/Translation/Exceptions/TranslationValidationFailedException.php`

Infrastructure component: `TranslationValidationFailedException`.

## `modules/Shared/Infrastructure/Translation/Exceptions/UnsupportedLocalePairException.php`

Infrastructure component: `UnsupportedLocalePairException`.

## `modules/Shared/Infrastructure/Translation/Facades/Translation.php`

Infrastructure component: `Translation`.

## `modules/Shared/Infrastructure/Translation/FluentTranslator.php`

Infrastructure component: `FluentTranslator`.

## `modules/Shared/Infrastructure/Translation/Jobs/TranslateModelJob.php`

Infrastructure component: `TranslateModelJob`.

## `modules/Shared/Infrastructure/Translation/LocaleResolver.php`

Infrastructure component: `LocaleResolver`.

## `modules/Shared/Infrastructure/Translation/Prompts/ClassificationPrompt.php`

Infrastructure component: `ClassificationPrompt`.

## `modules/Shared/Infrastructure/Translation/Prompts/ModerationPrompt.php`

Infrastructure component: `ModerationPrompt`.

## `modules/Shared/Infrastructure/Translation/Prompts/SummarizationPrompt.php`

Infrastructure component: `SummarizationPrompt`.

## `modules/Shared/Infrastructure/Translation/Providers/CircuitBreakerProvider.php`

Infrastructure component: `CircuitBreakerProvider`.

## `modules/Shared/Infrastructure/Translation/Providers/GeminiTranslationProvider.php`

Infrastructure component: `GeminiTranslationProvider`.

## `modules/Shared/Infrastructure/Translation/Providers/LoggingProvider.php`

Infrastructure component: `LoggingProvider`.

## `modules/Shared/Infrastructure/Translation/Providers/NullProvider.php`

Infrastructure component: `NullProvider`.

## `modules/Shared/Infrastructure/Translation/Traits/AutoTranslates.php`

Infrastructure component: `AutoTranslates`.

## `modules/Shared/Infrastructure/Translation/TranslationManager.php`

Infrastructure component: `TranslationManager`.

## `modules/Shared/Infrastructure/Translation/TranslationPrompt.php`

Infrastructure component: `TranslationPrompt`.

## `modules/Shared/Infrastructure/Translation/TranslationRegistry.php`

Infrastructure component: `TranslationRegistry`.

## `modules/Shared/Infrastructure/Translation/TranslationServiceProvider.php`

Infrastructure component: `TranslationServiceProvider`.

## `modules/Shared/Infrastructure/config/core.php`

PHP bootstrap or configuration source.

## `modules/Shared/Infrastructure/config/localization.php`

PHP bootstrap or configuration source.

## `modules/Shared/Infrastructure/config/tenancy.php`

PHP bootstrap or configuration source.

## `modules/Shared/Infrastructure/config/translation.php`

PHP bootstrap or configuration source.

## `modules/Shared/Presentation/Exceptions/ApiExceptionRenderer.php`

PHP class: `ApiExceptionRenderer`.

## `modules/Shared/Presentation/Http/Controllers/ApiController.php`

HTTP controller: `ApiController`.

## `modules/Shared/Presentation/Http/Controllers/EnumController.php`

HTTP controller: `EnumController`.

## `modules/Shared/Presentation/Http/Controllers/HealthController.php`

HTTP controller: `HealthController`.

## `modules/Shared/Presentation/Http/Middleware/ForceJsonResponse.php`

PHP class: `ForceJsonResponse`.

## `modules/Shared/Presentation/Http/Middleware/IdempotencyMiddleware.php`

PHP class: `IdempotencyMiddleware`.

## `modules/Shared/Presentation/Http/Middleware/ResolveTenant.php`

PHP class: `ResolveTenant`.

## `modules/Shared/Presentation/Http/Middleware/SetLocale.php`

PHP class: `SetLocale`.

## `modules/Shared/Presentation/Traits/ApiResponses.php`

PHP trait: `ApiResponses`.

## `modules/Shared/Presentation/routes/api.php`

PHP bootstrap or configuration source.

## `modules/Territory/Domain/Events/ZoneStatusChanged.php`

Domain event: `ZoneStatusChanged`.

## `modules/Territory/Domain/Models/City.php`

Domain model: `City`.

## `modules/Territory/Domain/Models/Country.php`

Domain model: `Country`.

## `modules/Territory/Domain/Models/District.php`

Domain model: `District`.

## `modules/Territory/Domain/Models/Governorate.php`

Domain model: `Governorate`.

## `modules/Territory/Domain/Models/Zone.php`

Domain model: `Zone`.

## `modules/Territory/Infrastructure/Console/Commands/CheckDuplicatesCommand.php`

Console command: `CheckDuplicatesCommand`.

## `modules/Territory/Infrastructure/Database/Migrations/2025_01_01_000030_create_countries_table.php`

Database schema migration: 2025_01_01_000030_create_countries_table

## `modules/Territory/Infrastructure/Database/Migrations/2025_01_01_000031_create_governorates_table.php`

Database schema migration: 2025_01_01_000031_create_governorates_table

## `modules/Territory/Infrastructure/Database/Migrations/2025_01_01_000032_create_cities_table.php`

Database schema migration: 2025_01_01_000032_create_cities_table

## `modules/Territory/Infrastructure/Database/Migrations/2025_01_01_000033_create_districts_table.php`

Database schema migration: 2025_01_01_000033_create_districts_table

## `modules/Territory/Infrastructure/Database/Migrations/2025_01_01_000034_create_zones_table.php`

Database schema migration: 2025_01_01_000034_create_zones_table

## `modules/Territory/Infrastructure/Database/Migrations/2026_07_14_000002_add_unique_index_to_zones_table.php`

Database schema migration: 2026_07_14_000002_add_unique_index_to_zones_table

## `modules/Territory/Infrastructure/Database/Seeders/TerritorySeeder.php`

Database seeder: `TerritorySeeder`.

## `modules/Territory/Infrastructure/Persistence/Filters/TerritoryFilter.php`

Infrastructure component: `TerritoryFilter`.

## `modules/Territory/Infrastructure/Persistence/Repositories/CityRepository.php`

Infrastructure component: `CityRepository`.

## `modules/Territory/Infrastructure/Persistence/Repositories/CountryRepository.php`

Infrastructure component: `CountryRepository`.

## `modules/Territory/Infrastructure/Persistence/Repositories/DistrictRepository.php`

Infrastructure component: `DistrictRepository`.

## `modules/Territory/Infrastructure/Persistence/Repositories/GovernorateRepository.php`

Infrastructure component: `GovernorateRepository`.

## `modules/Territory/Infrastructure/Persistence/Repositories/ZoneRepository.php`

Infrastructure component: `ZoneRepository`.

## `modules/Territory/Infrastructure/Providers/TerritoryServiceProvider.php`

Laravel service provider: `TerritoryServiceProvider`.

## `modules/Territory/Infrastructure/Services/CityService.php`

Infrastructure service: `CityService`.

## `modules/Territory/Infrastructure/Services/CountryService.php`

Infrastructure service: `CountryService`.

## `modules/Territory/Infrastructure/Services/DistrictService.php`

Infrastructure service: `DistrictService`.

## `modules/Territory/Infrastructure/Services/GovernorateService.php`

Infrastructure service: `GovernorateService`.

## `modules/Territory/Infrastructure/Services/ZoneService.php`

Infrastructure service: `ZoneService`.

## `modules/Territory/Presentation/Http/Controllers/Api/V1/TerritoryController.php`

HTTP controller: `TerritoryController`.

## `modules/Territory/Presentation/Http/Resources/CityResource.php`

API resource: `CityResource`.

## `modules/Territory/Presentation/Http/Resources/CountryResource.php`

API resource: `CountryResource`.

## `modules/Territory/Presentation/Http/Resources/DistrictResource.php`

API resource: `DistrictResource`.

## `modules/Territory/Presentation/Http/Resources/GovernorateResource.php`

API resource: `GovernorateResource`.

## `modules/Territory/Presentation/Http/Resources/ZoneResource.php`

API resource: `ZoneResource`.

## `modules/Territory/Presentation/Policies/CityPolicy.php`

Authorization policy: `CityPolicy`.

## `modules/Territory/Presentation/Policies/CountryPolicy.php`

Authorization policy: `CountryPolicy`.

## `modules/Territory/Presentation/Policies/DistrictPolicy.php`

Authorization policy: `DistrictPolicy`.

## `modules/Territory/Presentation/Policies/GovernoratePolicy.php`

Authorization policy: `GovernoratePolicy`.

## `modules/Territory/Presentation/Policies/ZonePolicy.php`

Authorization policy: `ZonePolicy`.

## `modules/Territory/Presentation/routes/api.php`

PHP bootstrap or configuration source.

## `modules/Wallet/Domain/Enums/WalletTransactionType.php`

PHP enum: `WalletTransactionType`.

## `modules/Wallet/Domain/Events/WalletCredited.php`

Domain event: `WalletCredited`.

## `modules/Wallet/Domain/Events/WalletDebited.php`

Domain event: `WalletDebited`.

## `modules/Wallet/Domain/Models/Wallet.php`

Domain model: `Wallet`.

## `modules/Wallet/Domain/Models/WalletTransaction.php`

Domain model: `WalletTransaction`.

## `modules/Wallet/Infrastructure/Database/Migrations/2025_01_01_000020_create_wallets_table.php`

Database schema migration: 2025_01_01_000020_create_wallets_table

## `modules/Wallet/Infrastructure/Providers/WalletServiceProvider.php`

Laravel service provider: `WalletServiceProvider`.

## `modules/Wallet/Infrastructure/Services/WalletService.php`

Infrastructure service: `WalletService`.

## `modules/Wallet/Presentation/Http/Controllers/Api/V1/WalletController.php`

HTTP controller: `WalletController`.

## `modules/Wallet/Presentation/Http/Resources/WalletResource.php`

API resource: `WalletResource`.

## `modules/Wallet/Presentation/Http/Resources/WalletTransactionResource.php`

API resource: `WalletTransactionResource`.

## `modules/Wallet/Presentation/Policies/WalletPolicy.php`

Authorization policy: `WalletPolicy`.

## `modules/Wallet/Presentation/routes/api.php`

PHP bootstrap or configuration source.

## `modules/Webhook/Domain/Models/WebhookDelivery.php`

Domain model: `WebhookDelivery`.

## `modules/Webhook/Domain/Models/WebhookEndpoint.php`

Domain model: `WebhookEndpoint`.

## `modules/Webhook/Infrastructure/Console/Commands/PublishOutboxMessages.php`

Console command: `PublishOutboxMessages`.

## `modules/Webhook/Infrastructure/Database/Migrations/2025_01_01_000022_create_webhook_tables.php`

Database schema migration: 2025_01_01_000022_create_webhook_tables

## `modules/Webhook/Infrastructure/Jobs/DeliverWebhook.php`

Infrastructure component: `DeliverWebhook`.

## `modules/Webhook/Infrastructure/Providers/WebhookServiceProvider.php`

Laravel service provider: `WebhookServiceProvider`.

## `modules/Webhook/Infrastructure/Resources/lang/ar/webhooks.php`

PHP bootstrap or configuration source.

## `modules/Webhook/Infrastructure/Resources/lang/en/webhooks.php`

PHP bootstrap or configuration source.

## `modules/Webhook/Infrastructure/Services/WebhookDispatcher.php`

Infrastructure service: `WebhookDispatcher`.

## `modules/Webhook/Infrastructure/config/webhook.php`

PHP bootstrap or configuration source.

## `modules/Webhook/Presentation/Http/Controllers/Api/V1/WebhookEndpointController.php`

HTTP controller: `WebhookEndpointController`.

## `modules/Webhook/Presentation/Http/Requests/StoreWebhookEndpointRequest.php`

HTTP request validator: `StoreWebhookEndpointRequest`.

## `modules/Webhook/Presentation/Http/Resources/WebhookEndpointResource.php`

API resource: `WebhookEndpointResource`.

## `modules/Webhook/Presentation/Policies/WebhookEndpointPolicy.php`

Authorization policy: `WebhookEndpointPolicy`.

## `modules/Webhook/Presentation/routes/api.php`

PHP bootstrap or configuration source.

## `phpstan.neon`

PHPStan static-analysis configuration.

## `phpunit.xml`

XML test-runner configuration.

## `phpunit_output.txt`

Project configuration or operational artifact.

## `public/index.php`

PHP bootstrap or configuration source.

## `query.log`

Project configuration or operational artifact.

## `routes/api.php`

PHP bootstrap or configuration source.

## `routes/channels.php`

PHP bootstrap or configuration source.

## `routes/console.php`

PHP bootstrap or configuration source.

## `storage/app/.gitignore`

Project configuration or operational artifact.

## `storage/app/public/.gitignore`

Project configuration or operational artifact.

## `storage/framework/.gitignore`

Project configuration or operational artifact.

## `storage/framework/cache/.gitignore`

Project configuration or operational artifact.

## `storage/framework/cache/data/.gitignore`

Project configuration or operational artifact.

## `storage/framework/sessions/.gitignore`

Project configuration or operational artifact.

## `storage/framework/views/.gitignore`

Project configuration or operational artifact.

## `storage/logs/.gitignore`

Project configuration or operational artifact.

## `test_output.txt`

Project configuration or operational artifact.

## `tests/Feature/Architecture/ConcurrencyAndRaceConditionTest.php`

Automated test coverage in the Feature/Architecture/ConcurrencyAndRaceConditionTest.php suite.

## `tests/Feature/Architecture/RbacTenantScopingTest.php`

Automated test coverage in the Feature/Architecture/RbacTenantScopingTest.php suite.

## `tests/Feature/Auth/AdvancedMfaAndSessionTest.php`

Automated test coverage in the Feature/Auth/AdvancedMfaAndSessionTest.php suite.

## `tests/Feature/Auth/AuthHardeningTest.php`

Automated test coverage in the Feature/Auth/AuthHardeningTest.php suite.

## `tests/Feature/Auth/AuthTest.php`

Automated test coverage in the Feature/Auth/AuthTest.php suite.

## `tests/Feature/Auth/DynamicAuthGovernanceTest.php`

Automated test coverage in the Feature/Auth/DynamicAuthGovernanceTest.php suite.

## `tests/Feature/Auth/EnterpriseSocialAuthTest.php`

Automated test coverage in the Feature/Auth/EnterpriseSocialAuthTest.php suite.

## `tests/Feature/Auth/FcmTokenTest.php`

Automated test coverage in the Feature/Auth/FcmTokenTest.php suite.

## `tests/Feature/Auth/NotificationFlowTest.php`

Automated test coverage in the Feature/Auth/NotificationFlowTest.php suite.

## `tests/Feature/Auth/OtpAuthTest.php`

Automated test coverage in the Feature/Auth/OtpAuthTest.php suite.

## `tests/Feature/Auth/PasswordRecoveryAndLockoutTest.php`

Automated test coverage in the Feature/Auth/PasswordRecoveryAndLockoutTest.php suite.

## `tests/Feature/Auth/PolicyAuthorizationTest.php`

Automated test coverage in the Feature/Auth/PolicyAuthorizationTest.php suite.

## `tests/Feature/Currency/CurrencyRegistryTest.php`

Automated test coverage in the Feature/Currency/CurrencyRegistryTest.php suite.

## `tests/Feature/Governance/SettingsTest.php`

Automated test coverage in the Feature/Governance/SettingsTest.php suite.

## `tests/Feature/Integration/PushNotificationTest.php`

Automated test coverage in the Feature/Integration/PushNotificationTest.php suite.

## `tests/Feature/Media/MediaUploadTest.php`

Automated test coverage in the Feature/Media/MediaUploadTest.php suite.

## `tests/Feature/Payment/PaymentFlowTest.php`

Automated test coverage in the Feature/Payment/PaymentFlowTest.php suite.

## `tests/Feature/RBAC/Api/RoleApiTest.php`

Automated test coverage in the Feature/RBAC/Api/RoleApiTest.php suite.

## `tests/Feature/RBAC/Api/UserAssignmentApiTest.php`

Automated test coverage in the Feature/RBAC/Api/UserAssignmentApiTest.php suite.

## `tests/Feature/RBAC/Authorization/AuthorizationContractTest.php`

Automated test coverage in the Feature/RBAC/Authorization/AuthorizationContractTest.php suite.

## `tests/Feature/RBAC/Authorization/EffectivePermissionTest.php`

Automated test coverage in the Feature/RBAC/Authorization/EffectivePermissionTest.php suite.

## `tests/Feature/RBAC/Authorization/PrivilegeEscalationTest.php`

Automated test coverage in the Feature/RBAC/Authorization/PrivilegeEscalationTest.php suite.

## `tests/Feature/RBAC/Authorization/TenantIsolationTest.php`

Automated test coverage in the Feature/RBAC/Authorization/TenantIsolationTest.php suite.

## `tests/Feature/RBAC/Infrastructure/AuditIntegrityTest.php`

Automated test coverage in the Feature/RBAC/Infrastructure/AuditIntegrityTest.php suite.

## `tests/Feature/RBAC/Infrastructure/CacheConsistencyTest.php`

Automated test coverage in the Feature/RBAC/Infrastructure/CacheConsistencyTest.php suite.

## `tests/Feature/RBAC/Infrastructure/IdempotencyAndTransactionTest.php`

Automated test coverage in the Feature/RBAC/Infrastructure/IdempotencyAndTransactionTest.php suite.

## `tests/Feature/RBAC/Infrastructure/PerformanceTest.php`

Automated test coverage in the Feature/RBAC/Infrastructure/PerformanceTest.php suite.

## `tests/Feature/RBAC/Infrastructure/RegistryCommandTest.php`

Automated test coverage in the Feature/RBAC/Infrastructure/RegistryCommandTest.php suite.

## `tests/Feature/RBAC/Lifecycle/MutationResistanceTest.php`

Automated test coverage in the Feature/RBAC/Lifecycle/MutationResistanceTest.php suite.

## `tests/Feature/RBAC/Lifecycle/RecoveryTest.php`

Automated test coverage in the Feature/RBAC/Lifecycle/RecoveryTest.php suite.

## `tests/Feature/RBAC/Lifecycle/RoleLifecycleTest.php`

Automated test coverage in the Feature/RBAC/Lifecycle/RoleLifecycleTest.php suite.

## `tests/Feature/RBAC/Security/SecurityRegressionTest.php`

Automated test coverage in the Feature/RBAC/Security/SecurityRegressionTest.php suite.

## `tests/Feature/Security/EnterpriseRbacPolicyTest.php`

Automated test coverage in the Feature/Security/EnterpriseRbacPolicyTest.php suite.

## `tests/Feature/System/AutoTranslationTest.php`

Automated test coverage in the Feature/System/AutoTranslationTest.php suite.

## `tests/Feature/System/BroadcastingTest.php`

Automated test coverage in the Feature/System/BroadcastingTest.php suite.

## `tests/Feature/System/EnumApiTest.php`

Automated test coverage in the Feature/System/EnumApiTest.php suite.

## `tests/Feature/System/HealthCheckTest.php`

Automated test coverage in the Feature/System/HealthCheckTest.php suite.

## `tests/Feature/Territory/QueryFilterTest.php`

Automated test coverage in the Feature/Territory/QueryFilterTest.php suite.

## `tests/Feature/Territory/TerritoryTest.php`

Automated test coverage in the Feature/Territory/TerritoryTest.php suite.

## `tests/Feature/Wallet/WalletTest.php`

Automated test coverage in the Feature/Wallet/WalletTest.php suite.

## `tests/Feature/Webhook/WebhookDeliveryTest.php`

Automated test coverage in the Feature/Webhook/WebhookDeliveryTest.php suite.

## `tests/Feature/Webhook/WebhookEndpointTest.php`

Automated test coverage in the Feature/Webhook/WebhookEndpointTest.php suite.

## `tests/Feature/Webhook/WebhookRateLimitTest.php`

Automated test coverage in the Feature/Webhook/WebhookRateLimitTest.php suite.

## `tests/Support/AuthorizationAssertions.php`

Automated test coverage in the Support/AuthorizationAssertions.php suite.

## `tests/Support/CreatesPermission.php`

Automated test coverage in the Support/CreatesPermission.php suite.

## `tests/Support/CreatesRole.php`

Automated test coverage in the Support/CreatesRole.php suite.

## `tests/Support/CreatesTenant.php`

Automated test coverage in the Support/CreatesTenant.php suite.

## `tests/Support/CreatesUser.php`

Automated test coverage in the Support/CreatesUser.php suite.

## `tests/TestCase.php`

Automated test coverage in the TestCase.php suite.

## `tests/Unit/Core/CircuitBreakerTest.php`

Automated test coverage in the Unit/Core/CircuitBreakerTest.php suite.

## `tests/Unit/Core/CoreAbstractsAndContractsTest.php`

Automated test coverage in the Unit/Core/CoreAbstractsAndContractsTest.php suite.

## `tests/Unit/MoneyTest.php`

Automated test coverage in the Unit/MoneyTest.php suite.


