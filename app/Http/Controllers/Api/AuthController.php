<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Register a new user
     * Supports both existing tenant (tenant_id) and creating new tenant (tenant_name)
     */
    public function register(Request $request): Response
    {
        // Updated validation - support both tenant_id and tenant_name
        $validator = Validator::make($request->all(), [
            'tenant_id' => ['nullable', 'uuid', 'exists:tenants,id'],
            'tenant_name' => ['nullable', 'string', 'max:255', 'min:2'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            return response([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate that either tenant_id or tenant_name is provided
        if (!$request->has('tenant_id') && !$request->has('tenant_name')) {
            return response([
                'message' => 'Either tenant_id or tenant_name is required'
            ], 400);
        }

        // Get or create tenant
        $tenant = null;

        if ($request->has('tenant_id')) {
            // Use existing tenant
            $tenant = Tenant::find($request->tenant_id);
            if (!$tenant || !$tenant->is_active) {
                return response([
                    'message' => 'Invalid or inactive tenant'
                ], 400);
            }
        } elseif ($request->has('tenant_name')) {
            // Create new tenant from tenant_name
            $slug = Str::slug($request->tenant_name);

            // Check if tenant with this slug already exists
            $existingTenant = Tenant::where('slug', $slug)->first();
            if ($existingTenant) {
                // Tenant exists, use it
                $tenant = $existingTenant;
                if (!$tenant->is_active) {
                    return response([
                        'message' => 'Tenant exists but is inactive'
                    ], 400);
                }
            } else {
                // Create new tenant
                $tenant = Tenant::create([
                    'name' => $request->tenant_name,
                    'slug' => $slug,
                    'is_active' => true,
                    'settings' => [],
                ]);
            }
        }

        // Check if email already exists in this tenant
        $existingUser = User::where('tenant_id', $tenant->id)
            ->where('email', $request->email)
            ->first();

        if ($existingUser) {
            return response([
                'message' => 'Email already exists in this tenant'
            ], 409);
        }

        // Create user with admin role for new tenant, viewer for existing
        $role = $request->has('tenant_name') ? 'admin' : 'viewer';

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role,
            'is_active' => true,
        ]);

        // Assign role using Spatie
        $user->assignRole($role);

        // Create API token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response([
            'message' => 'User registered successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
            ],
            'token' => $token,
        ], 201);
    }

    /**
     * Login user
     */
    public function login(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'tenant_id' => ['nullable', 'uuid'],
        ]);

        if ($validator->fails()) {
            return response([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        // Add tenant_id to credentials for multi-tenant authentication
        if ($request->has('tenant_id')) {
            $credentials['tenant_id'] = $request->tenant_id;
        }

        // Find user by email and tenant
        $user = User::where('email', $request->email)
            ->when($request->has('tenant_id'), fn($q) => $q->where('tenant_id', $request->tenant_id))
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response([
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$user->is_active) {
            return response([
                'message' => 'User account is inactive'
            ], 403);
        }

        // Check tenant is active
        if (!$user->tenant || !$user->tenant->is_active) {
            return response([
                'message' => 'Tenant is inactive'
            ], 403);
        }

        // Revoke old tokens
        $user->tokens()->delete();

        // Create new API token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response([
            'message' => 'Login successful',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'tenant_id' => $user->tenant_id,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): Response
    {
        // Revoke current token
        $request->user()->currentAccessToken()->delete();

        return response([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request): Response
    {
        $user = $request->user()->load(['tenant', 'roles', 'permissions']);

        return response([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'role' => $user->role,
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'tenant' => [
                    'id' => $user->tenant->id,
                    'name' => $user->tenant->name,
                    'slug' => $user->tenant->slug,
                ],
            ],
        ]);
    }
}
