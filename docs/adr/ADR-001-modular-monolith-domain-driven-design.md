# ADR-001: Modular Monolith and Domain-Driven Design (DDD) Architecture

**Status:** Approved & Implemented  
**Date:** 2026-07-14  
**Context:** Enterprise Production Readiness  

## 1. Context and Problem Statement
As the enterprise platform scales across multiple domains (Authentication, Governance, Media, Payment, RBAC, Territory, Wallet, Webhooks), a traditional MVC Laravel folder structure (`app/Models`, `app/Controllers`, `app/Services`) creates tight horizontal coupling, domain bleed, and monolithic entanglement. Future microservice extraction becomes high-risk, and team ownership boundaries become blurred.

## 2. Decision
We adopt a **Strict Modular Monolith Architecture** organized around **Domain-Driven Design (DDD)** principles:
- **Core (`app/Core/`)**: Cross-cutting kernel concepts (Tenancy, Outbox, Traits, Shared Middlewares).
- **Domain (`app/Domain/[BoundedContext]/`)**: Self-contained business domains (`Auth`, `Governance`, `Media`, `Payment`, `RBAC`, `Territory`, `Wallet`, `Webhook`). Each domain contains its own `Models`, `Services`, `Repositories`, `Events`, `Listeners`, `Http/Controllers`, and `Policies`.
- **Infrastructure (`app/Infrastructure/`)**: External technical capabilities without business logic (`Translation`, `Storage`, etc.).

## 3. Consequences
### Positive
- **Strict Encapsulation:** Business logic stays completely inside its respective domain folder.
- **Microservice Readiness:** Any bounded context (e.g., `Payment` or `Wallet`) can be extracted into an independent service with minimal refactoring.
- **Clear Ownership:** Engineering pods can own entire domain namespaces without stepping on each other's code.

### Mitigations Required
- Strict PHPStan / static analysis boundary checking to ensure domains do not perform unauthorized cross-domain database queries directly bypassing domain service contracts.
