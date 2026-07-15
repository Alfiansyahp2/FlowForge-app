# FlowForge

![Build Status](https://img.shields.io/badge/build-passing-success)
![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue)
![Laravel Version](https://img.shields.io/badge/Laravel-12.x-red)
![React Version](https://img.shields.io/badge/React-18.x-blue)
![TypeScript](https://img.shields.io/badge/TypeScript-5.x-blue)
![License](https://img.shields.io/badge/license-MIT-green)

FlowForge is a high-performance, multi-tenant workflow orchestration engine. Designed to automate and manage complex business processes, FlowForge allows teams to define, execute, and monitor automated workflows in real-time using visual Directed Acyclic Graphs (DAGs).

## Features

- **Multi-Tenant Architecture**: Strict data isolation and role-based access control (RBAC).
- **Visual Workflow Editor**: Interactive drag-and-drop canvas powered by React Flow for designing workflows easily.
- **Advanced Workflow Engine**: Full support for Directed Acyclic Graphs (DAGs) with parallel execution, cycle detection, and topological sorting on the backend.
- **Robust Execution**: Built-in exponential backoff retry logic, global timeout management, and safe mathematical/logical expression evaluations.
- **Flexible Triggers**: Support for manual execution, cron-based scheduling, and webhook-triggered flows.
- **Clean Architecture**: Strong backend separation of concerns with Services, Actions, DTOs, API Resources, and Form Requests.

## Tech Stack

**Backend**:
- Laravel 12.x (PHP 8.3+)
- PostgreSQL (Database & JSONB storage for workflow states)
- Laravel Sanctum (Authentication)
- PHPStan & Laravel Pint (Code Quality)
- Pest / PHPUnit (Testing)

**Frontend**:
- React 18 & Vite
- TypeScript
- Tailwind CSS & Shadcn UI (Styling & Components)
- Zustand (State Management)
- React Flow (Node-based Visual Editor)
- Zod (Schema Validation)
- ESLint & Prettier (Code Quality)

## Quick Start

### Prerequisites
- PHP 8.3+
- Composer
- Node.js (v18+) & npm
- PostgreSQL

### Backend Setup (Laravel)

1. Clone the repository and install dependencies:
   ```bash
   git clone https://github.com/your-org/flowforge.git
   cd flowforge
   composer install
   ```

2. Configure your environment:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. Setup your database in `.env` (e.g. `DB_CONNECTION=pgsql`), then run migrations and seeders:
   ```bash
   php artisan migrate --seed
   ```

4. Start the backend server and schedule worker (for cron workflows):
   ```bash
   php artisan serve
   php artisan schedule:work
   ```

### Frontend Setup (React/Vite)

1. Open a new terminal tab and navigate to the frontend directory:
   ```bash
   cd frontend
   ```

2. Install dependencies:
   ```bash
   npm install
   ```

3. Setup environment variables:
   ```bash
   cp .env.example .env
   # Update VITE_API_URL if your backend is not running on http://localhost:8000
   ```

4. Start the development server:
   ```bash
   npm run dev
   ```

## Documentation

Comprehensive documentation is available in the [`docs/`](./docs) directory:

- [Architecture Overview](./docs/ARCHITECTURE.md)
- [Development Guide](./docs/DEVELOPMENT.md)
- [Docker Setup & Deployment](./docs/DOCKER_SETUP.md)
- [Real-Time Monitoring Guide](./docs/REAL_TIME_MONITORING_GUIDE.md)

## Testing

**Backend:**
```bash
php artisan test
# Static Analysis & Formatting
./vendor/bin/phpstan analyse
./vendor/bin/pint
```

**Frontend:**
```bash
npm run type-check
npm run lint
```

## Contributing

We welcome contributions! Please see our [Development Guide](./docs/DEVELOPMENT.md) for details on how to get started, our coding standards, and the pull request process.

## License

This project is licensed under the MIT License.
