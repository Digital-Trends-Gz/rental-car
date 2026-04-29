<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->redirectGuestsTo(function (\Illuminate\Http\Request $request): string {
            $host = strtolower($request->getHost());
            $baseHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
            $pathSegments = array_values(array_filter(explode('/', trim($request->path(), '/'))));
            $availableLocales = config('app.available_locales', [config('app.locale', 'en')]);

            if (!empty($pathSegments) && in_array($pathSegments[0], $availableLocales, true)) {
                array_shift($pathSegments);
            }

            if (($pathSegments[0] ?? null) === 'superadmin') {
                return route('superadmin.login');
            }

            if ($baseHost !== '' && str_ends_with($host, '.'.$baseHost)) {
                return route('tenant-login');
            }

            return route('login');
        });

        $middleware->web(prepend: [
            \App\Http\Middleware\IdentifyTenant::class,
            \App\Http\Middleware\EnforceSecurityAccess::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'payment/webhooks/subscriptions/*',
        ]);

        $middleware->alias([
            'super_admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'client' => \App\Http\Middleware\ClientMiddleware::class,
            'active' => \App\Http\Middleware\CheckUserActive::class,
            'tenant_verified' => \App\Http\Middleware\EnsureTenantEmailIsVerified::class,
            'tenant.subscription' => \App\Http\Middleware\EnsureTenantSubscriptionIsActive::class,
            'tenant.feature' => \App\Http\Middleware\EnsureTenantFeatureEnabled::class,
            'restricted' => \App\Http\Middleware\restricted::class,
            'can_manage_roles' => \App\Http\Middleware\CanManageRoles::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
             /**** OTHER MIDDLEWARE ALIASES ****/
            'localize'                => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes::class,
            'localizationRedirect'    => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            'localeSessionRedirect'   => \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            'localeCookieRedirect'    => \Mcamara\LaravelLocalization\Middleware\LocaleCookieRedirect::class,
            'localeViewPath'          => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath::class,
     
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\Throwable $e, Request $request) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : (int) $e->getCode();

            if ($status === 423) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $e->getMessage() ?: 'This resource is locked.',
                    ], 423);
                }

                return back()->with('error', $e->getMessage() ?: 'This resource is locked.');
            }

            return null;
        });
    })->create();
