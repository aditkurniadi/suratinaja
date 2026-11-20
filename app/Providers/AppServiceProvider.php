<?php

namespace App\Providers;

use App\Models\Letter;
use App\Models\LetterTemplate;
use App\Models\Workspace;
use App\Policies\LetterPolicy;
use App\Policies\LetterTemplatePolicy;
use App\Policies\WorkspacePolicy;
use App\Support\CurrentWorkspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use PhpOffice\PhpWord\Settings as PhpWordSettings;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentWorkspace::class, fn () => new CurrentWorkspace);
    }

    public function boot(): void
    {
        if ($this->app->environment('production') && str_starts_with((string) config('app.url', ''), 'https://')) {
            URL::forceScheme('https');
        }

        Carbon::setLocale(config('app.locale', 'id'));
        PhpWordSettings::setOutputEscapingEnabled(true);

        Gate::before(function ($user, $ability) {
            return $user->is_super_admin ? true : null;
        });

        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Letter::class, LetterPolicy::class);
        Gate::policy(LetterTemplate::class, LetterTemplatePolicy::class);
        Gate::policy(\App\Models\SuratMasuk::class, \App\Policies\SuratMasukPolicy::class);
        Gate::policy(\App\Models\Disposisi::class, \App\Policies\DisposisiPolicy::class);

        // DemoGuard: Prevent database writes/deletes for demo users
        $guardedModels = [
            \App\Models\Letter::class,
            \App\Models\LetterRevision::class,
            \App\Models\LetterActivity::class,
            \App\Models\SuratMasuk::class,
            \App\Models\Disposisi::class,
            \App\Models\LetterTemplate::class,
            \App\Models\EnvelopeTemplate::class,
            \App\Models\Workspace::class,
            \App\Models\WorkspaceMember::class,
            \App\Models\WorkspaceInvitation::class,
            \App\Models\BulkJob::class,
            \App\Models\User::class,
        ];

        foreach ($guardedModels as $modelClass) {
            $modelClass::observe(\App\Observers\DemoGuardObserver::class);
        }
    }
}
