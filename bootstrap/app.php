<?php

use App\Http\Middleware\EnsureActiveWorkspace;
use App\Http\Middleware\EnsureIsSuperAdmin;
use App\Http\Middleware\EnsureUserNotSuspended;
use App\Http\Middleware\EnsureWorkspaceMember;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: [
            'api/onlyoffice/*',
        ]);
        $middleware->web(append: [
            EnsureUserNotSuspended::class,
        ]);
        $middleware->alias([
            'workspace.active' => EnsureActiveWorkspace::class,
            'workspace.member' => EnsureWorkspaceMember::class,
            'is_super_admin' => EnsureIsSuperAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\App\Exceptions\DemoModeRestrictedException $e, $request) {
            if ($request->header('X-Livewire')) {
                return response()->json([
                    'demo_restricted' => true,
                    'message' => $e->getMessage(),
                ], 422);
            }

            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'DEMO_RESTRICTED',
                        'message' => $e->getMessage(),
                    ],
                ], 403);
            }

            return back()->with('error', $e->getMessage());
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => $e->getMessage(),
                        'details' => $e->errors(),
                    ],
                ], 422);
            }
        });
    })->create();
