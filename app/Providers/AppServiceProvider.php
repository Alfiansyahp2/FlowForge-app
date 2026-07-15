<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Services\TenantService;
use App\WorkflowEngine\NodeRegistry;
use App\WorkflowEngine\Nodes\ConditionNodeExecutor;
use App\WorkflowEngine\Nodes\DelayNodeExecutor;
use App\WorkflowEngine\Nodes\HttpNodeExecutor;
use App\WorkflowEngine\Nodes\MathNodeExecutor;
use App\WorkflowEngine\Nodes\NotificationNodeExecutor;
use App\WorkflowEngine\Nodes\ScriptNodeExecutor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind TenantService as singleton to maintain tenant state across requests
        $this->app->singleton(TenantService::class);

        // Bind NodeRegistry as singleton
        $this->app->singleton(NodeRegistry::class, function ($app) {
            $registry = new NodeRegistry;

            // Register all executable nodes
            $registry->register($app->make(HttpNodeExecutor::class));
            $registry->register($app->make(DelayNodeExecutor::class));
            $registry->register($app->make(ConditionNodeExecutor::class));
            $registry->register($app->make(ScriptNodeExecutor::class));
            $registry->register($app->make(MathNodeExecutor::class));
            $registry->register($app->make(NotificationNodeExecutor::class));

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Standard API Rate Limiter
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Anti Brute-Force Rate Limiter for Authentication
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
