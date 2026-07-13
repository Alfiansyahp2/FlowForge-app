# FlowForge

![Build Status](https://img.shields.io/badge/build-passing-success)
![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue)
![Laravel Version](https://img.shields.io/badge/Laravel-12.x-red)
![License](https://img.shields.io/badge/license-MIT-green)

FlowForge is a high-performance, multi-tenant workflow orchestration engine. Designed to automate and manage complex business processes, FlowForge allows teams to define, execute, and monitor automated workflows in real-time using Directed Acyclic Graphs (DAGs).

## Features

- **Multi-Tenant Architecture**: Strict data isolation and role-based access control (RBAC).
- **Advanced Workflow Engine**: Full support for Directed Acyclic Graphs (DAGs) with parallel execution, cycle detection, and topological sorting.
- **Robust Execution**: Built-in exponential backoff retry logic and global timeout management.
- **Real-Time Monitoring**: Live execution tracking via WebSocket/SSE integrations.
- **Flexible Triggers**: Support for manual execution, cron-based scheduling, and webhooks.
- **RESTful API**: Comprehensive API endpoints with rate limiting, filtering, and pagination.

## Quick Start

### Prerequisites
- PHP 8.3+
- Composer
- PostgreSQL
- Redis
- Docker & Docker Compose (Optional, for containerized setup)

### Installation (Local)

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

3. Run database migrations:
   ```bash
   php artisan migrate
   ```

4. Start the application:
   ```bash
   php artisan serve
   ```

### Installation (Docker)

For a quick, isolated setup using Docker:

```bash
docker-compose up -d
docker-compose exec app php artisan migrate
```

## Documentation

Comprehensive documentation is available in the [`docs/`](./docs) directory:

- [Architecture Overview](./docs/ARCHITECTURE.md)
- [Development Guide](./docs/DEVELOPMENT.md)
- [Docker Setup & Deployment](./docs/DOCKER_SETUP.md)
- [Real-Time Monitoring Guide](./docs/REAL_TIME_MONITORING_GUIDE.md)

## API Reference

The REST API allows seamless integration with third-party services. Detailed API specifications and usage examples can be found in our comprehensive documentation suite.

## Testing

FlowForge maintains a robust test suite using Pest. To run tests:

```bash
./vendor/bin/pest
```

## Contributing

We welcome contributions! Please see our [Development Guide](./docs/DEVELOPMENT.md) for details on how to get started, our coding standards, and the pull request process.

## License

This project is licensed under the MIT License.
