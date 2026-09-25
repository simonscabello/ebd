<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Push\NullPushSender;
use App\Support\Push\PushSender;
use App\Support\Push\WebPushSender;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Passport\Passport;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // O MCP usa só authorization code + refresh token; sem as rotas /oauth/device.
        Passport::$deviceCodeGrantEnabled = false;

        $this->app->bind(PushSender::class, function (): PushSender {
            $public = (string) config('ebd.push.public_key');
            $private = (string) config('ebd.push.private_key');

            return $public !== '' && $private !== ''
                ? new WebPushSender((string) config('ebd.push.subject'), $public, $private, $this->app->make(LoggerInterface::class))
                : new NullPushSender;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureHealthcheck();

        // URLs em português: /admin/licoes/criar, /admin/licoes/1/editar
        Route::resourceVerbs(['create' => 'criar', 'edit' => 'editar']);

        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configureOAuth();
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

        // Atrás do proxy do Railway, garante links e redirects sempre em HTTPS.
        URL::forceHttps(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(10)
                ->letters()
                ->numbers()
                ->uncompromised()
            : null,
        );
    }

    /**
     * O endpoint /up (healthcheck do deploy) também confirma que o banco responde.
     * Em caso de falha retorna 500 sem detalhes (APP_DEBUG=false).
     */
    protected function configureHealthcheck(): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            DB::connection()->select('select 1');
        });
    }

    protected function configureAuthorization(): void
    {
        Gate::define('access-admin', fn (User $user) => $user->canAccessAdmin());
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('downloads', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Tentativas de entrar por link pessoal: limite por IP (por minuto e por dia).
        RateLimiter::for('access-link', fn (Request $request) => [
            Limit::perMinute(10)->by('min:'.$request->ip()),
            Limit::perDay(50)->by('day:'.$request->ip()),
        ]);

        RateLimiter::for('engagement', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('library', fn (Request $request) => Limit::perMinute(90)->by($request->user()?->id ?: $request->ip()));

        // Agentes de IA fazem várias chamadas seguidas numa mesma conversa.
        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }

    /**
     * OAuth (Passport) do servidor MCP: tela de consentimento em português e
     * tokens curtos, renovados sozinhos pelo aplicativo do agente.
     */
    protected function configureOAuth(): void
    {
        Passport::authorizationView('mcp.authorize');
        Passport::tokensExpireIn(now()->addDay());
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}
