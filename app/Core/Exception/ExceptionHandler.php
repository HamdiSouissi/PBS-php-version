<?php

declare(strict_types=1);

namespace App\Core\Exception;

use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Logger\Logger;
use App\Core\View\ViewRenderer;
use Throwable;

/**
 * Gestion centralisée des exceptions : réponses JSON pour l'API,
 * pages d'erreur pour le web, journalisation systématique.
 */
final class ExceptionHandler
{
    public function __construct(
        private readonly Logger $logger,
        private readonly ViewRenderer $viewRenderer,
        private readonly bool $debug
    ) {
    }

    public function handle(Throwable $exception, Request $request): Response
    {
        $status = $this->statusFor($exception);
        $context = [
            'path' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ];

        if ($status >= 500) {
            $this->logger->error($exception->getMessage(), [
                ...$context,
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        } else {
            $this->logger->warning($exception->getMessage(), $context);
        }

        if ($request->isApi()) {
            return $this->jsonResponse($exception, $status);
        }
        return $this->htmlResponse($exception, $status);
    }

    private function jsonResponse(Throwable $exception, int $status): JsonResponse
    {
        $payload = [
            'success' => false,
            'error' => [
                'type' => $exception::class,
                'code' => $status,
                'message' => $status >= 500 && !$this->debug
                    ? 'Une erreur interne est survenue.'
                    : $exception->getMessage(),
            ],
        ];
        if ($exception instanceof ValidationException) {
            $payload['error']['fields'] = $exception->errors();
        }
        if ($this->debug) {
            $payload['error']['file'] = $exception->getFile() . ':' . $exception->getLine();
            $payload['error']['trace'] = explode("\n", $exception->getTraceAsString());
        }
        return new JsonResponse($payload, $status);
    }

    private function htmlResponse(Throwable $exception, int $status): Response
    {
        try {
            $html = $this->viewRenderer->renderTemplate('errors/generic', [
                'status' => $status,
                'message' => $status >= 500 && !$this->debug ? 'Une erreur interne est survenue.' : $exception->getMessage(),
                'exception' => $this->debug ? $exception : null,
            ], 'layouts/minimal');
        } catch (Throwable) {
            $html = $this->fallbackHtml($status, $exception);
        }
        return new Response($html, $status);
    }

    private function fallbackHtml(int $status, Throwable $exception): string
    {
        $message = $this->debug ? $exception->getMessage() : 'Une erreur est survenue.';
        return "<!doctype html><html lang=\"fr\"><head><meta charset=\"utf-8\"><title>Erreur {$status}</title></head>"
            . "<body style=\"font-family:sans-serif;padding:3rem\"><h1>Erreur {$status}</h1><p>"
            . htmlspecialchars($message, ENT_QUOTES) . '</p></body></html>';
    }

    private function statusFor(Throwable $exception): int
    {
        if ($exception instanceof HttpException) {
            return $exception->getStatusCode();
        }
        if ($exception instanceof AppException) {
            return 422;
        }
        return 500;
    }
}
