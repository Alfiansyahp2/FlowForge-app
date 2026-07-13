# FlowForge Development Guide

This guide outlines the development setup, guidelines, and best practices for contributing to the FlowForge platform.

## Quick Start

### Prerequisites
- **PHP**: 8.3+
- **Composer**: 2.x
- **Node.js**: 20+
- **PostgreSQL**: 16+
- **Redis**: 7+
- **Docker**: 20.10+ (optional but recommended for isolated environments)

### Initial Setup

```bash
# Clone repository
git clone https://github.com/your-username/flowforge.git
cd flowforge

# Install backend dependencies
composer install

# Install frontend dependencies
cd frontend && npm install && cd ..

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate
php artisan db:seed

# Build frontend
cd frontend && npm run build && cd ..

# Start development server
php artisan serve
```

**Access:** http://localhost:8000

## Project Structure

```text
flowforge/
├── app/                          # Laravel application core
│   ├── Broadcasting/             # WebSocket channels & events
│   ├── Console/Commands/         # Custom CLI commands
│   ├── Http/                     # API Controllers & Middleware
│   ├── Models/                   # Data models
│   ├── Services/                 # Business logic
│   └── WorkflowEngine/           # Workflow orchestration logic
├── database/                     # Migrations and seeders
├── frontend/                     # React application
│   ├── src/components/           # UI Components
│   └── src/pages/                # Application views
├── docker/                       # Docker environment configurations
└── tests/                        # Automated test suites
```

## Testing

FlowForge maintains strict testing requirements to ensure stability.

### Backend Tests (Pest & PHPUnit)

```bash
# Run all tests
./vendor/bin/pest

# Run with coverage report
./vendor/bin/pest --coverage

# Run specific suite
./vendor/bin/pest --testsuite=Unit
```

### Frontend Tests (Jest / RTL)

```bash
cd frontend
npm test
npm run test:coverage
npm run type-check
```

## Code Style & Standards

We enforce strict coding standards across the stack to maintain readability and reduce bugs.

### PHP Standards (PSR-12)

```bash
# Check code style
./vendor/bin/pint --test

# Auto-fix styling issues
./vendor/bin/pint
```

### TypeScript Standards

```bash
cd frontend
npm run lint
npm run lint:fix
```

## Development Workflow

### Branching Strategy

We follow a structured Git flow:
- `main` → Production-ready code
- `develop` → Primary integration branch
- `feature/*` → New feature development
- `bugfix/*` → Non-critical fixes
- `hotfix/*` → Urgent production fixes

### Commit Guidelines

Use Conventional Commits format:
```text
type(scope): subject
```
**Types:** `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

**Examples:**
- `feat(engine): implement parallel execution batching`
- `fix(auth): resolve token parsing issue on edge cases`

### Pull Request Process

1. Create a feature branch from `develop`.
2. Ensure all commits are atomic and descriptive.
3. Verify that all tests pass locally.
4. Submit the PR for review, linking any relevant issues.
5. Address review feedback promptly.