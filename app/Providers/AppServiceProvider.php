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
        if (config('app.env') === 'production' || str_starts_with(config('app.url', ''), 'https://')) {
            URL::forceScheme('https');
        }

        Carbon::setLocale(config('app.locale', 'id'));
        PhpWordSettings::setOutputEscapingEnabled(true);

        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Letter::class, LetterPolicy::class);
        Gate::policy(LetterTemplate::class, LetterTemplatePolicy::class);
    }
}
