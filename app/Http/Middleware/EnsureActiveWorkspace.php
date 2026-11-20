<?php

namespace App\Http\Middleware;

use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $workspace = $user->activeWorkspace();
        if (! $workspace) {
            if (! $request->routeIs('workspaces.onboarding', 'invitations.accept', 'logout')) {
                return redirect()->route('workspaces.onboarding');
            }

            return $next($request);
        }

        app(CurrentWorkspace::class)->set($workspace);
        setPermissionsTeamId($workspace->id);
        app(\App\Services\Authorization\WorkspacePermissionRegistrar::class)->ensureUserRoleSynced($workspace, $user);

        return $next($request);
    }
}
