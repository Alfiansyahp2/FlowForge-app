# FlowForge Docker Setup Guide

This document provides complete instructions for deploying FlowForge using Docker. It covers both local development environments and production-grade deployments.

## Quick Start

### Prerequisites
- Docker Engine 20.10+
- Docker Compose 2.0+
- 4GB RAM minimum
- 10GB available disk space

### Basic Setup

1. **Clone and navigate to the project directory:**
```bash
git clone https://github.com/your-username/flowforge.git
cd flowforge
```

2. **Initialize environment configuration:**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Build and start services:**
```bash
make build
make up
```

4. **Run database migrations and seeders:**
```bash
make migrate
make seed
```

5. **Access the application:**
- **Frontend**: http://localhost:8000
- **API Base**: http://localhost:8000/api
- **Horizon Dashboard**: http://localhost:8080/horizon

## Available Services

### Development Stack
- **app**: Laravel PHP-FPM application
- **nginx**: Nginx web server acting as a reverse proxy
- **postgres**: PostgreSQL 16 database
- **redis**: Redis for caching and queue management
- **horizon**: Laravel Horizon queue supervisor
- **scheduler**: Laravel task scheduler for cron jobs
- **reverb**: Laravel Reverb WebSocket server

### Production Stack (Additional)
- **prometheus**: Metrics collection and aggregation
- **grafana**: Monitoring and observability dashboard

## Command Reference (Makefile)

### Development Lifecycle
```bash
make build        # Build containers
make up           # Start services in detached mode
make down         # Stop and remove containers
make restart      # Restart all services
make logs         # Tail service logs
make shell        # Open an interactive shell in the app container
```

### Database Management
```bash
make migrate      # Run pending migrations
make seed         # Seed database with initial data
make fresh        # Drop all tables and re-migrate
make db-fresh     # Drop, re-migrate, and seed
make backup       # Create a database dump
```

### Production Operations
```bash
make prod         # Start the production stack
make monitor      # Start the monitoring stack
make status       # Check health status of all services
make stats        # View container resource usage
```

## Configuration

### Environment Variables

Ensure your `.env` file is properly configured for the Docker network:

```env
# Application
APP_NAME=FlowForge
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-generated-key
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=flowforge
DB_USERNAME=flowforge
DB_PASSWORD=your-secure-password

# Redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=

# Laravel Reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
```

### Production Deployment

1. Review and update `.env` with production secrets.
2. Build and start the production stack:
```bash
make prod
```
3. Enable the monitoring stack (optional but recommended):
```bash
make monitor
```

## Troubleshooting

### Container Issues

**Permission Errors:**
If storage directories are inaccessible:
```bash
sudo chown -R $USER:$USER storage bootstrap/cache
```

**Database Connection Failures:**
Ensure the PostgreSQL container is fully initialized before the app container attempts to connect.
```bash
docker-compose logs postgres
```

### Performance Tuning

**PHP-FPM Optimization:**
Adjust `docker/nginx/default.conf` to handle larger request buffers if needed:
```nginx
fastcgi_buffers 32 32k;
fastcgi_buffer_size 64k;
```

**PostgreSQL Optimization:**
Modify `docker/postgres/postgresql.conf` based on available system memory:
```ini
shared_buffers = 256MB
effective_cache_size = 1GB
max_connections = 100
```

## Security Requirements

Before deploying to production, ensure the following steps are completed:
- Change all default database and service passwords.
- Verify `APP_DEBUG` is set to `false`.
- Enable SSL/TLS (HTTPS) via Nginx or an external load balancer.
- Configure strict firewall rules restricting access to internal ports (e.g., 5432, 6379).
- Establish automated backup routines for the PostgreSQL volume.
- Review and apply standard security headers in the Nginx configuration.

## Scaling

### Horizontal Scaling

Worker nodes can be scaled horizontally to handle increased workflow execution load:

```bash
# Scale queue workers
docker-compose up -d --scale horizon=3
```

To scale the application servers, ensure a load balancer is configured in your `docker-compose.prod.yml` to distribute traffic across the Replicas.