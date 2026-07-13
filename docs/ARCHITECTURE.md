# FlowForge Architecture Documentation

This document outlines the technical architecture, design decisions, and infrastructure for the FlowForge multi-tenant workflow orchestration platform.

## System Architecture

### Core Components

```text
┌─────────────────────────────────────────────────────────────┐
│                      Frontend Layer                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │  React App   │  │  React Flow  │  │  TailwindCSS │       │
│  └──────────────┘  └──────────────┘  └──────────────┘       │
└─────────────────────────────────────────────────────────────┘
                            ↓ HTTPS
┌─────────────────────────────────────────────────────────────┐
│                     API Gateway                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │   Nginx      │  │  Rate Limit  │  │   Sanctum    │       │
│  └──────────────┘  └──────────────┘  └──────────────┘       │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│                   Application Layer                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │ Laravel App  │  │   Workflow   │  │   Reverb     │       │
│  │              │  │   Engine     │  │  (Websocket) │       │
│  └──────────────┘  └──────────────┘  └──────────────┘       │
└─────────────────────────────────────────────────────────────┘
         ↓                    ↓                    ↓
┌─────────────────────────────────────────────────────────────┐
│                   Data & Infrastructure                     │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │ PostgreSQL   │  │    Redis     │  │   Horizon    │       │
│  │  (Primary)   │  │  (Cache/Queue)│  │  (Worker)    │       │
│  └──────────────┘  └──────────────┘  └──────────────┘       │
└─────────────────────────────────────────────────────────────┘
```

## Technology Stack & Rationale

### Backend: Laravel 12 + PHP 8.3
**Rationale:**
- **Rapid Development**: Built-in authentication, queues, and broadcasting.
- **Ecosystem**: Rich package ecosystem (Sanctum, Horizon, Reverb).
- **Maturity**: Battle-tested for enterprise applications with expressive syntax and strong conventions.

### Frontend: React + TypeScript
**Rationale:**
- **Component Architecture**: Reusable UI components suitable for complex dashboards.
- **Ecosystem**: Rich library support, particularly React Flow for DAG visualization.
- **Type Safety**: TypeScript provides compile-time error checking, essential for large codebases.

### Database: PostgreSQL 16
**Rationale:**
- **JSONB Support**: Native storage capabilities for dynamic workflow definitions.
- **ACID Compliance**: Ensures data integrity for multi-tenant operations.
- **Advanced Features**: Window functions, CTEs, and sophisticated indexing strategies support complex orchestration queries.

### Cache/Queue: Redis 7
**Rationale:**
- **Performance**: In-memory operations provide sub-millisecond latency.
- **Versatility**: Utilized for caching, pub/sub communication, queues, and session management.
- **Scalability**: Capable of horizontal scaling with clustering and persistence mechanisms.

## Multi-Tenant Architecture

### Tenant Isolation Strategy

Tenant data isolation is enforced at the database query level using Global Scopes.

**Implementation Example:**
```php
class Workflow extends Model {
    protected static function booted() {
        static::addGlobalScope(new TenantScope);
    }
}
```

**Data Flow:**
Incoming Request → IdentifyTenant Middleware → Set Current Tenant Context → Global Scope Application → Tenant-Filtered Queries → Response.

## Workflow Engine Architecture

### Core Components
- `WorkflowValidator`: Schema validation.
- `CycleDetector`: Cycle detection using Depth-First Search (DFS).
- `TopologicalSorter`: Dependency resolution for DAG execution.
- `WorkflowExecutor`: Orchestration of step execution.
- `SafeExpressionEvaluator`: Secure parsing of conditional expressions.

### Execution Strategy

**Parallel Batch Processing:**
1. **Topological Sort**: Determine execution order based on node dependencies.
2. **Batch Grouping**: Group independent nodes into execution batches.
3. **Parallel Execution**: Execute batches concurrently where possible.
4. **Result Aggregation**: Collect and pass results to dependent nodes.

## Event Broadcasting Architecture

Real-time updates are handled via Laravel Reverb using dedicated WebSocket channels.

**Channels:**
- `workflows.{workflowId}`: For workflow-specific execution events.
- `tenant.{tenantId}`: For tenant-wide orchestration events.

## Database Design & Optimization

### Schema Overview
The relational structure follows a hierarchy from Tenants to Workflows and their respective Execution Runs.

**Key Design Decisions:**
- **UUID Primary Keys**: Ensures distributed system compatibility.
- **JSONB Storage**: Provides flexible structure for workflow nodes and edges.
- **Denormalized Foreign Keys**: Optimizes performance for high-frequency queries.

### Indexing Strategy
Compound indexes are utilized to optimize standard dashboard queries:
```sql
CREATE INDEX idx_workflow_runs_dashboard ON workflow_runs(tenant_id, status, started_at);
```

## Deployment Architecture

### Production Stack
Internet → Nginx (SSL/Termination) → PHP-FPM → Laravel App → PostgreSQL + Redis

**Process Management:**
- **Horizon**: Manages queue workers for background processing.
- **Scheduler**: Orchestrates cron job execution for scheduled workflows.
- **Reverb**: Manages WebSocket connections for the real-time monitoring dashboard.

## Future Architecture Roadmap

### Short-Term Objectives
- Implement robust workflow state persistence for long-running tasks.
- Enhance multi-process execution via advanced queue worker pools.
- Integrate comprehensive monitoring and observability tools (Prometheus/Grafana).
- Deploy strict API rate limiting across all endpoints.

### Long-Term Objectives
- Decompose core engine components into microservices.
- Transition to event sourcing for an immutable audit log of workflow execution.
- Introduce a GraphQL API for dynamic frontend data querying.
- Build an advanced analytics pipeline for workflow optimization insights.