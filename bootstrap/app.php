<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Atrás do Traefik: confia nos cabeçalhos X-Forwarded-* para HTTPS/IP real.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        // Webhooks externos não enviam token CSRF; autenticados por token próprio.
        $middleware->validateCsrfTokens(except: [
            'api/asaas/webhook',
            'api/whatsapp/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sessão expirada (419) em formulário longo (cadastro/checkout): volta
        // para a página com os campos preservados em vez da tela 419 genérica.
        // Precisa ser via HttpException: o Laravel converte TokenMismatchException
        // em HttpException(419) antes de chamar os renderers registrados aqui.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 419);
            }

            return redirect()->back()
                ->withInput($request->except(['password', 'password_confirmation', 'current_password']))
                ->withErrors(['session' => 'Sua sessão expirou por segurança. Seus dados foram mantidos — confira e envie de novo.']);
        });
    })->create();
