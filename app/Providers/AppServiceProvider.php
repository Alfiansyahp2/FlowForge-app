<?php

namespace App\Providers;

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
        $this->app->singleton(\App\Services\TenantService::class);

        // Bind NodeRegistry as singleton
        $this->app->singleton(\App\WorkflowEngine\NodeRegistry::class, function ($app) {
            $registry = new \App\WorkflowEngine\NodeRegistry();
            
            // Register all executable nodes
            $registry->register($app->make(\App\WorkflowEngine\Nodes\HttpNodeExecutor::class));
            $registry->register($app->make(\App\WorkflowEngine\Nodes\DelayNodeExecutor::class));
            $registry->register($app->make(\App\WorkflowEngine\Nodes\ConditionNodeExecutor::class));
            $registry->register($app->make(\App\WorkflowEngine\Nodes\ScriptNodeExecutor::class));
            $registry->register($app->make(\App\WorkflowEngine\Nodes\MathNodeExecutor::class));
            $registry->register($app->make(\App\WorkflowEngine\Nodes\NotificationNodeExecutor::class));

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        // Standard API Rate Limiter
        \Illuminate\Support\Facades\RateLimiter::for('api', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Anti Brute-Force Rate Limiter for Authentication
        \Illuminate\Support\Facades\RateLimiter::for('auth', function (\Illuminate\Http\Request $request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($request->ip());
        });
    }
}
