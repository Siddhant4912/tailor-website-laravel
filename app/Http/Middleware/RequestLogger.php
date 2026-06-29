<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        try {

            $logFile = storage_path('logs/request_debug.log');

            $data = [
                'time' => now()->toDateTimeString(),
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
            ];

            file_put_contents(
                $logFile,
                json_encode($data) . PHP_EOL,
                FILE_APPEND
            );

        } catch (\Throwable $e) {
            // ignore logging errors
        }

        return $next($request);
    }
}