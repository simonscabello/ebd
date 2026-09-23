<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // URLs em português: /admin/licoes/criar, /admin/licoes/1/editar
        Route::resourceVerbs(['create' => 'criar', 'edit' => 'editar']);

        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Detecta N+1 e atributos descartados silenciosamente fora de produção.
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());

        // Resources são usados como props do Inertia e, no futuro, pela API REST.
        // Sem o envelope "data" as props ficam diretas; paginação mantém data/links/meta.
        JsonResource::withoutWrapping();

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(10)
                ->letters()
                ->numbers()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthorization(): void
    {
        Gate::define('access-admin', fn (User $user) => $user->canAccessAdmin());
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('downloads', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('library', fn (Request $request) => Limit::perMinute(90)->by($request->user()?->id ?: $request->ip()));
    }
}
