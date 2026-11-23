<?php

use App\Http\Middleware\EnsureOrgAdmin;
use App\Http\Middleware\PreventWebhookReplay;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifyWebhookSignature;
use App\Services\SecurityEventLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'org.admin' => EnsureOrgAdmin::class,
            'verified.webhook' => VerifyWebhookSignature::class,
            'webhook.replay' => PreventWebhookReplay::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Lab 7 G1: JSON errors for the API.
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Lab 7 G3: AuthorizationException is on Laravel's default "do not report" list, so it must be
        // un-ignored or the report callback below would never run.
        $exceptions->stopIgnoring(AuthorizationException::class);

        $exceptions->report(function (AuthorizationException $e) {
            app(SecurityEventLogger::class)->record('authorization_denied', [
                'user_id' => request()->user()?->id,
                'route' => request()->route()?->getName(),
                'path' => request()->path(),
                'ip' => request()->ip(),
            ]);

            return false; // already logged; skip the default report
        });

        // Lab 7 G5: Laravel's 404 message for a missing model names the model class
        // ("No query results for model [App\\Models\\Order] 99"). Return a generic message instead.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });
    })->create();
