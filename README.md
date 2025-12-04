# Split Jar

## Overview

Split Jar is a financial management application designed to track personal and shared expenses with advanced flexibility and automation. The project aims to provide a clean, scalable, and domain-driven backend built on Laravel, with the long-term goal of evolving into a distributed system using CQRS, event-driven architecture, and microservices.

The system focuses on three primary capabilities:

- **Expense Tracking**: Users can manually create expenses or generate them automatically from images (OCR + AI extraction).
- **Shared Expense Management**: Expenses can belong to groups (e.g., household, friends, trips). Each group allows custom split weights (e.g., 60/40 based on income).
- **Debt Settlement Logic**: The platform computes who owes who, based on individual contributions, weights, and expense classifications.

The project begins as a monolithic Laravel API with simple server-rendered templates for early iterations. Later, the frontend will be migrated to Flutter.

This repository is structured using DDD principles, clean code, extensive testing, and a roadmap toward more advanced architectural patterns.

## ✨ Features (Current & Planned)

### ✅ Initial Features

- Manual expense creation
- Expense creation from uploaded receipt/photos via AI
- User accounts and authentication
- Group creation (household, couples, friends, etc.)
- Custom weight distribution per member (e.g., 60/40)
- Expense categorization (personal vs shared)
- Debt calculation engine
- Basic web UI templates in Laravel (temporary)

### 🧠 AI Capabilities

- OCR and extraction of:
  - Vendor
  - Date
  - Total amount
  - Line items (future)

### 🔜 Future Features

- Flutter mobile app consuming the API
- CQRS segregation of commands and queries
- Event-driven domain workflows
- Microservices for:
  - AI processing
  - Calculation engine
  - Notifications
- API versioning
- JWT or Paseto authentication tokens
- Real-time updates (WebSockets / SSE)

## 🏛 Architecture

The project implements **Hexagonal Architecture (Ports & Adapters)** with **Domain-Driven Design (DDD)** principles. This hybrid approach combines the power of Laravel with clean domain logic.

### Bounded Contexts

The system is organized into **2 Bounded Contexts** for clarity and separation of concerns:

#### 1. **User BC** - Identity & Access Management
- **Responsibility:** Authentication, authorization, user profiles
- **Aggregates:** User
- **Independence:** Does not depend on Split BC

#### 2. **Split BC** - Core Business Logic
- **Responsibility:** Shared expense management
- **Aggregates:** Expense, Group
- **Domain Services:** SettlementCalculator
- **Dependencies:** Depends on User BC for identity (UserId)

### Directory Structure

```
/app                                    # Laravel layer (Framework)
  /Models                               # Eloquent models (Active Record)
    ├── User.php
    ├── Expense.php
    └── Group.php
  /Http
    /Controllers                        # HTTP entry points
    /Middleware

/src                                    # Domain layer (DDD)
  /User                                 # User Bounded Context
    /User                               # User Aggregate
      /Domain
        ├── User.php                    # Domain Entity (POPO)
        ├── UserId.php                  # Value Object
        ├── Email.php                   # Value Object
        └── UserRepositoryInterface.php # Port (interface)
      /Application
        /RegisterUser
          ├── RegisterUserCommand.php
          └── RegisterUserHandler.php
        /GetUser
          ├── GetUserQuery.php
          └── GetUserHandler.php
      /Infrastructure
        /Persistence
          ├── EloquentUserRepository.php
          └── UserMapper.php

  /Split                                # Split Bounded Context
    /Expense                            # Expense Aggregate Root
      /Domain
        ├── Expense.php                 # Domain Entity (POPO)
        ├── ExpenseId.php               # Value Object
        ├── Money.php                   # Value Object
        ├── Currency.php                # Enum/Value Object
        ├── ExpenseCategory.php         # Enum
        └── ExpenseRepositoryInterface.php # Port
      /Application
        /CreateExpense
          ├── CreateExpenseCommand.php
          └── CreateExpenseHandler.php
        /GetExpenses
          ├── GetExpensesQuery.php
          └── GetExpensesHandler.php
      /Infrastructure
        /Persistence
          ├── EloquentExpenseRepository.php
          └── ExpenseMapper.php

    /Group                              # Group Aggregate Root
      /Domain
        ├── Group.php                   # Domain Entity
        ├── GroupId.php                 # Value Object
        ├── Member.php                  # Value Object
        ├── Weight.php                  # Value Object
        └── GroupRepositoryInterface.php
      /Application
        /CreateGroup
          ├── CreateGroupCommand.php
          └── CreateGroupHandler.php
        /AddMember
          ├── AddMemberCommand.php
          └── AddMemberHandler.php
      /Infrastructure
        /Persistence
          ├── EloquentGroupRepository.php
          └── GroupMapper.php

    /Shared                             # Shared elements within Split BC
      /Domain
        /Services
          └── SettlementCalculator.php  # Domain Service
        /ValueObjects
          ├── Settlement.php            # Read model/DTO
          └── Transaction.php           # Value Object
        /Base
          ├── AggregateRoot.php         # Base aggregate
          ├── Entity.php                # Base entity
          ├── ValueObject.php           # Base value object
          └── DomainException.php       # Domain exceptions
```

### Architectural Decisions

#### 1. **Hexagonal Architecture (Ports & Adapters)**

The domain is isolated from the framework through interfaces (ports):

```php
// Domain defines the interface (Port)
interface ExpenseRepositoryInterface {
    public function save(Expense $expense): void;
    public function findById(ExpenseId $id): ?Expense;
}

// Infrastructure implements it (Adapter)
class EloquentExpenseRepository implements ExpenseRepositoryInterface {
    public function save(Expense $expense): void {
        $eloquentModel = ExpenseMapper::toEloquent($expense);
        $eloquentModel->save();
    }
}
```

**Benefits:**
- ✅ Domain logic is **pure PHP** (no Laravel dependencies)
- ✅ Easy to test domain without database
- ✅ Can swap Laravel for another framework without touching domain
- ✅ Clear separation of concerns

#### 2. **Dual Model Approach: /app + /src**

We maintain **two representations** of each entity:

| Layer | Location | Type | Purpose |
|-------|----------|------|---------|
| **Domain Entity** | `/src/Domain/Expense/Expense.php` | POPO (Plain Old PHP Object) | Business rules, immutability, validation |
| **Eloquent Model** | `/app/Models/Expense.php` | Active Record | Database persistence, relationships, queries |

**Why both?**
- Domain entities are **immutable** and **framework-agnostic**
- Eloquent models give us **Laravel features** (factories, observers, scopes)
- Mapper layer translates between them

#### 3. **Mapping Layer**

Mappers convert between Eloquent and Domain entities:

```php
class ExpenseMapper {
    public static function toDomain(ExpenseModel $model): Expense {
        return new Expense(
            ExpenseId::fromString($model->id),
            new Money($model->amount, Currency::from($model->currency)),
            $model->date,
            ExpenseCategory::from($model->category),
            UserId::fromString($model->user_id)
        );
    }

    public static function toEloquent(Expense $expense): ExpenseModel {
        return new ExpenseModel([
            'id' => $expense->id()->value(),
            'amount' => $expense->money()->amount(),
            'currency' => $expense->money()->currency()->value,
            // ...
        ]);
    }
}
```

#### 4. **CQRS Lite (Command/Query Separation)**

Application layer separates writes (Commands) from reads (Queries):

- **Commands**: Modify state, use domain entities
- **Queries**: Read data, can bypass domain and query Eloquent directly for performance

### Architectural Goals

- ✅ **Keep business logic pure** - Domain layer has zero Laravel dependencies
- ✅ **Application orchestrates use cases** - Handlers coordinate domain and infrastructure
- ✅ **Infrastructure adapts framework** - Repositories and mappers bridge Laravel ↔ Domain
- ✅ **Testability** - Unit test domain without database, integration test with Laravel
- ✅ **Flexibility** - Can migrate to microservices or change frameworks
- ✅ **Laravel benefits** - Still leverage Eloquent, factories, queues, events

### Trade-offs

**We gain:**
- Clean, testable domain logic
- Framework independence
- Long-term maintainability

**We pay:**
- More initial setup
- Mapping overhead (Eloquent ↔ Entity)
- Two representations of data

**Why it's worth it for this project:**
- Complex domain logic (settlement calculations, weight distribution)
- Long-term goal: microservices + CQRS
- Business rules need to be testable in isolation

## 🧪 Testing Strategy

Split Jar is built following:

- Unit tests for domain services, aggregations, and entities.
- Feature tests for HTTP endpoints.
- Contract tests for future microservice boundaries.
- TDD, optionally assisted by AI to auto-generate test suites from natural definitions of behavior.
- Mutation testing (planned) to ensure test robustness.

## 🚀 Getting Started

### Requirements

- PHP 8.4+
- Composer
- Laravel 12+
- PostgreSQL
- Node 24
- Optional: OpenAI / Claude / local LLM for OCR/AI expense extraction

### Setup

```bash
# TODO
git clone https://github.com/<your-org>/split-jar.git
cd split-jar
composer install
# etc...
```


## 🧩 Domain Model Design

### Aggregates & Entities

#### 1. Expense Aggregate

**Root Entity: `Expense`**

Domain entity (POPO):
```php
class Expense {
    private ExpenseId $id;
    private Money $money;            // amount + currency
    private DateTimeImmutable $date;
    private ExpenseCategory $category;
    private UserId $paidBy;
    private ?GroupId $groupId;
    private ?string $description;
    private ?string $vendor;
}
```

**Value Objects:**
- `ExpenseId` - UUID identifier
- `Money` - amount (decimal) + Currency (EUR, USD, etc.)
- `Currency` - Enum (ISO 4217 codes)
- `ExpenseCategory` - Enum (FOOD, TRANSPORT, UTILITIES, etc.)

**Business Rules:**
- Amount must be positive
- Date cannot be in the future
- If linked to a group, the payer must be a member
- Currency must match group currency (or be convertible)

#### 2. Group Aggregate

**Root Entity: `Group`**

Domain entity:
```php
class Group {
    private GroupId $id;
    private string $name;
    private Currency $currency;
    private UserId $createdBy;
    private Members $members;        // Collection of Member VOs
    private Weights $weights;        // Collection of Weight VOs
}
```

**Value Objects:**
- `GroupId` - UUID identifier
- `Member` - userId + joinDate
- `Weight` - userId + weight (decimal, e.g., 0.6 for 60%)
- `Members` - Collection (ensures uniqueness, validates weights)
- `Weights` - Collection (validates sum = 1.0)

**Business Rules:**
- Group must have at least 2 members
- Weights must sum to 1.0 (100%)
- Each member can have only one weight at a time
- Creator is automatically a member with default weight
- Cannot remove last admin/creator

#### 3. User Entity

**Entity: `User`**

Domain representation:
```php
class User {
    private UserId $id;
    private string $name;
    private Email $email;
}
```

**Value Objects:**
- `UserId` - UUID identifier
- `Email` - validated email address

**Note:** Full user management (auth, passwords) lives in `/app/Models/User.php`. Domain only cares about identity.

#### 4. Settlement (Read Model)

**Not an aggregate** - computed from expenses and groups.

```php
class Settlement {
    private GroupId $groupId;
    private array $balances;         // userId => decimal
    private array $transactions;     // [creditor, debtor, amount]
}
```

**Computation:**
1. Calculate each member's balance (paid - owed weighted)
2. Use settlement algorithm to minimize transactions
3. Return list of "X owes Y amount"

### Domain Services

#### `SettlementCalculator`

Pure domain service that computes settlements:

```php
interface SettlementCalculator {
    public function calculate(Group $group, array $expenses): Settlement;
}
```

**Algorithm:**
1. Get all expenses for the group
2. Calculate total expenses
3. Apply weights to determine each member's share
4. Calculate balances: `paid - (total * weight)`
5. Optimize transactions using greedy algorithm

**Example:**
- Total: €100
- Alice paid €100, Bob paid €0
- Weights: 60/40
- Balances: Alice +€40, Bob -€40
- Settlement: Bob owes Alice €40

#### `WeightValidator`

Validates weight distributions:
- Sum equals 1.0 (with floating-point tolerance)
- All weights are positive
- Each member has exactly one weight

### Database Schema (Eloquent Models)

**Mapping to PostgreSQL:**

```sql
-- /app/Models/Expense.php → expenses table
expenses (
    id UUID PRIMARY KEY,
    user_id UUID REFERENCES users(id),
    group_id UUID REFERENCES groups(id) NULL,
    amount DECIMAL(10,2),
    currency VARCHAR(3),
    date DATE,
    category VARCHAR(50),
    description TEXT NULL,
    vendor VARCHAR(255) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)

-- /app/Models/Group.php → groups table
groups (
    id UUID PRIMARY KEY,
    name VARCHAR(255),
    currency VARCHAR(3),
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
)

-- /app/Models/GroupMember.php → group_members table
group_members (
    id UUID PRIMARY KEY,
    group_id UUID REFERENCES groups(id),
    user_id UUID REFERENCES users(id),
    weight DECIMAL(3,2),
    joined_at TIMESTAMP,
    UNIQUE(group_id, user_id)
)
```

**Eloquent Relationships:**
- `Expense belongsTo User (paidBy)`
- `Expense belongsTo Group (optional)`
- `Group belongsTo User (creator)`
- `Group hasMany GroupMember`
- `User hasMany Expense`
- `User belongsToMany Group through GroupMember`

### API Design (MVP)

**Expense Endpoints:**
```
POST   /api/v1/expenses          - Create expense
GET    /api/v1/expenses          - List user's expenses
GET    /api/v1/expenses/{id}     - Get expense details
PUT    /api/v1/expenses/{id}     - Update expense
DELETE /api/v1/expenses/{id}     - Delete expense
```

**Group Endpoints:**
```
POST   /api/v1/groups                    - Create group
GET    /api/v1/groups                    - List user's groups
GET    /api/v1/groups/{id}               - Get group details
PUT    /api/v1/groups/{id}               - Update group
DELETE /api/v1/groups/{id}               - Delete group
POST   /api/v1/groups/{id}/members       - Add member
DELETE /api/v1/groups/{id}/members/{uid} - Remove member
PUT    /api/v1/groups/{id}/weights       - Update weights
GET    /api/v1/groups/{id}/settlement    - Get settlement
```

**Authentication:**
```
POST   /api/v1/auth/register     - Register user
POST   /api/v1/auth/login        - Login (returns token)
POST   /api/v1/auth/logout       - Logout (revoke token)
GET    /api/v1/auth/me           - Get current user
```

**Tech:** Laravel Sanctum (stateless token-based auth)