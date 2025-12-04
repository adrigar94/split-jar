# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Split Jar** is a financial management application for tracking personal and shared expenses with advanced flexibility and automation. Built with Laravel 12 and PostgreSQL, it follows **Hexagonal Architecture (Ports & Adapters)** combined with **Domain-Driven Design (DDD)** principles.

## Architecture: Hexagonal DDD with Dual Model Approach

### Core Principle: `/app` + `/src` Strategy

The project maintains **two representations** of each entity:

- **Domain Entities** (`/src`) - Pure PHP objects (POPOs), framework-agnostic, contain business rules
- **Eloquent Models** (`/app/Models`) - Active Record pattern, handle database persistence

**Mapping Layer:** `Infrastructure/Persistence/Mappers` converts between Eloquent models and Domain entities.

### Bounded Contexts

The system is organized into **2 Bounded Contexts**:

1. **User BC** (`/src/User`) - Identity & Access Management
   - Independent, no dependencies on other BCs

2. **Split BC** (`/src/Split`) - Core Business Logic
   - Aggregates: `Expense`, `Group`
   - Domain Services: `SettlementCalculator` (in `/Split/Shared/Domain/Services`)
   - Depends on User BC for identity (UserId)

### Directory Structure Pattern

Each entity follows this structure:
```
/src/{BoundedContext}/{Entity}/
  /Domain                    # Pure business logic (POPOs)
    - {Entity}.php          # Domain entity
    - {Entity}Id.php        # Value object
    - {Entity}RepositoryInterface.php  # Port (interface)
  /Application               # Use cases
    /Create{Entity}
      - Create{Entity}Command.php
      - Create{Entity}Handler.php
  /Infrastructure            # Adapters
    /Persistence
      - Eloquent{Entity}Repository.php
      - {Entity}Mapper.php  # Eloquent ↔ Domain mapping
```

Eloquent models live in `/app/Models/{Entity}.php` with UUIDs (using `HasUuids` trait).

## Common Commands

### Development
```bash
# Full development environment (server + queue + logs + vite)
composer dev

# Individual services
php artisan serve              # Start dev server
php artisan queue:listen       # Start queue worker
php artisan pail              # View logs
npm run dev                   # Start Vite dev server
```

### Testing
```bash
composer test                  # Run all tests
php artisan test              # Run PHPUnit tests
php artisan test --filter=TestName  # Run specific test
php artisan test tests/Feature/ExampleTest.php  # Run specific file
```

### Code Quality
```bash
./vendor/bin/pint             # Format code (Laravel Pint)
```

### Database
```bash
php artisan migrate           # Run migrations
php artisan migrate:fresh     # Drop all tables and re-run
php artisan migrate:rollback  # Rollback last batch
```

### DDD Structure Generation
```bash
php artisan new:entity {BoundedContext} {Entity}
# Example: php artisan new:entity Split Expense
# Creates: /src/{BC}/{Entity}/Domain|Application|Infrastructure with .gitkeep
#          /app/Models/{Entity}.php with UUIDs
```

## Key Architecture Rules

### 1. Domain Layer (`/src/*/Domain`)
- **Zero Laravel dependencies** - Pure PHP only
- Entities are **immutable** (use constructor + private properties)
- Business rules enforced in entities/value objects
- Define repository **interfaces** (ports), not implementations

### 2. Application Layer (`/src/*/Application`)
- Orchestrates use cases using **Command/Query handlers**
- Commands: Modify state (use domain entities)
- Queries: Read data (can query Eloquent directly for performance)
- Handlers coordinate domain + infrastructure

### 3. Infrastructure Layer (`/src/*/Infrastructure`)
- Implements repository interfaces (adapters)
- Mappers convert: `Eloquent → Domain Entity` (toDomain) and `Domain Entity → Eloquent` (toEloquent)
- All Laravel/framework-specific code lives here

### 4. Eloquent Models (`/app/Models`)
- Use `HasUuids` trait for all domain entities
- Define relationships, scopes, observers here
- Keep thin - business logic belongs in domain

### 5. Cross-BC Communication
- User BC → Split BC: Use `UserId` value object (not User entity)
- Never import domain entities from other bounded contexts
- Consider events for loose coupling (future)

## Database Configuration

- **Database:** PostgreSQL (configured in `.env`)
- **Test Database:** SQLite in-memory (`:memory:`)
- **Primary Keys:** UUIDs for all domain entities
- **Migrations:** Use `$table->uuid('id')->primary()` for entities

## Autoloading

Only `/app`, `/database`, `/tests` are autoloaded by default. If you add classes to `/src`:

```json
// composer.json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Split\\": "src/Split/",
        "User\\": "src/User/"
    }
}
```

Then run: `composer dump-autoload`

## Testing Strategy

- **Unit tests** (`tests/Unit`): Test domain entities, value objects, services **without** database
- **Feature tests** (`tests/Feature`): Test HTTP endpoints, use RefreshDatabase trait
- **Repository tests**: Test mappers and Eloquent integration
- Test database uses SQLite in-memory for speed

## MVP Priorities (Current Phase)

Focus on **velocity** for MVP:
1. Manual expense CRUD (no OCR/AI yet)
2. Groups with weighted splits (60/40, etc)
3. Settlement calculation engine
4. API with Sanctum authentication

Future: OCR/AI expense extraction, Flutter mobile app, CQRS, microservices.

## Critical Files

- `/app/Console/Commands/NewEntity.php` - DDD structure generator
- `/README.md` - Complete architecture documentation with code examples
- `/.env` - Database: `split_jar` (PostgreSQL), user: `root`, pass: `toor`

## Notes on Implementation

- When creating new entities, always use `php artisan new:entity` command first
- Repository implementations should always use mappers, never return Eloquent directly from domain methods
- Value objects should extend base class in `/src/{BC}/Shared/Domain/Base/ValueObject.php` (when created)
- Settlement calculation is a **domain service**, not an entity - it computes from Expenses and Groups
- All amounts use `Money` value object (amount + Currency), never raw decimals in domain
