# ADR-005: Provider-Agnostic AI Translation Infrastructure

**Status:** Approved & Implemented  
**Date:** 2026-07-14  
**Context:** Enterprise Production Readiness  

## 1. Context and Problem Statement
Our application requires automated bilingual parity (English/Arabic) for translatable domain models (`Zone`, `Country`, `Governorate`, `City`, `District`, `RoleMetadata`).
Previously, translation logic lived directly inside `App\Core\Translation\GeminiTranslationProvider` or inside domain traits without strict contracts, prompt versioning, circuit breaker protection, or decoupled queue processing.
If Gemini API rate limits (`HTTP 429`) or outages occurred during user-facing model creation, requests failed or timed out. If we wanted to switch providers (e.g., OpenAI, Claude, DeepL, or local LLMs), business code and domain models had to be rewritten.

## 2. Decision
1. **Infrastructure Namespace (`App\Infrastructure\Translation\`):** Moved all AI and translation capabilities out of `App\Core` and into `App\Infrastructure\Translation\`, recognizing AI translation as an external infrastructure concern.
2. **Provider Agnostic (`TranslationProviderInterface` & `Manager` Pattern):** Created `TranslationManager` extending Laravel's `Manager`, allowing dynamic driver selection (`config/translation.php` -> `default = gemini`). Custom drivers can be registered seamlessly using `$manager->extend('openai', ...)`.
3. **Prompt Contracts (`PromptInterface`):** Defined strict prompt contracts and specialized prompts (`TranslationPrompt`, `SummarizationPrompt`, `ClassificationPrompt`, `ModerationPrompt`) with explicit versioning (`v1`), ensuring cached responses stay consistent when prompt definitions evolve.
4. **Resilience & Circuit Breaker Decorator (`CircuitBreakerProvider`):** Created a universal `CircuitBreakerProvider` decorator that automatically wraps any registered `TranslationProviderInterface`. It monitors consecutive failures (`failure_threshold = 5`) and transitions state (`CLOSED` -> `OPEN` -> `HALF_OPEN`), blocking outgoing API calls during outages to protect worker threads.
5. **Decoupled Queue Pipeline & Telemetry (`TranslateModelJob` & `translation_jobs`):** Domain models dispatch `TranslateModelJob` to the `translations` queue. The queue worker enforces proactive rate limiting (`RateLimiter::for('translations')` -> `30 RPM`), tracks execution status (`pending`, `processing`, `completed`, `failed`) inside the `translation_jobs` table, and verifies UTF-8 structure and non-empty string integrity before populating database columns.

## 3. Consequences
### Positive
- **Future-Proof LLM Integration:** Swapping Gemini for OpenAI or Claude requires zero code changes outside `config/translation.php` or driver registration.
- **Resilient & Proactive:** Rate limits and outages are caught by proactive rate limiters and circuit breakers without crashing domain request threads.
- **Granular Telemetry:** Operations teams have visibility into translation latency, status, and failures via `translation_jobs` records and `TranslationCompleted`/`TranslationFailed` domain events.
