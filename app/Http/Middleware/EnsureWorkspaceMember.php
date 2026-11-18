<?php

namespace App\Http\Middleware;

use App\Exceptions\DomainException;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceMember
{
    public function handle(Request $request, Closure $next, ?string $capability = null): Response
    {
        $user = $request->user();
        if (! $user) {
            throw DomainException::workspaceForbidden();
        }

        $routeWorkspace = $request->route('workspace');
        if ($routeWorkspace instanceof Workspace) {
            $workspace = $routeWorkspace;
        } elseif ($routeWorkspace !== null) {
            $workspace = Workspace::find($routeWorkspace);
        } else {
            $workspace = app(CurrentWorkspace::class)->get() ?? $user->activeWorkspace();
        }

        if (! $workspace || ! $workspace->hasMember($user)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                throw DomainException::workspaceForbidden();
            }
            abort(403, 'Anda bukan anggota workspace ini.');
        }

        app(CurrentWorkspace::class)->set($workspace);

        if ($capability === 'write' && ! $workspace->roleOf($user)?->canWrite()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                throw DomainException::workspaceForbidden('Peran Ketua bersifat read-only dan tidak dapat mengubah data.');
            }
            abort(403, 'Peran Ketua bersifat read-only.');
        }

        if ($capability === 'admin' && ! $workspace->roleOf($user)?->isAdmin()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                throw DomainException::workspaceForbidden('Hanya Admin Workspace yang dapat mengakses fitur ini.');
            }
            abort(403, 'Hanya Admin Workspace yang dapat mengakses halaman ini.');
        }

        return $next($request);
    }
}
