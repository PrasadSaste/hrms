<?php

use App\Http\Middleware\EnsureBackgroundCheckCleared;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\LogActivity;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\RedirectIfInstalled;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'active' => EnsureUserIsActive::class,
            'guest' => RedirectIfAuthenticated::class,
            'password.change' => ForcePasswordChange::class,
            'log.activity' => LogActivity::class,
            'bgv.cleared' => EnsureBackgroundCheckCleared::class,
            'two-factor' => RequireTwoFactor::class,
            'installed' => RedirectIfInstalled::class,
        ]);

        // A system with no database behind it should land on the thing that
        // fixes that, whatever page was asked for.
        $middleware->web(append: [EnsureInstalled::class]);
        $middleware->api(append: [EnsureInstalled::class]);

        // Ahead of the sign-in check, or a deep link into an uninstalled
        // system asks a database that does not exist yet who is signed in and
        // sends people to a login page that cannot work. Appending is not
        // enough on its own: authentication carries a priority and would
        // otherwise be sorted in front of anything that does not. The list
        // names the contract rather than the class, so this does too.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EnsureInstalled::class);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'This action is unauthorized.',
                ], 403);
            }

            return null;
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return null;
        });
    })->create();
