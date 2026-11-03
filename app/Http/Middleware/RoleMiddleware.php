<?php

// namespace App\Http\Middleware;

// use Closure;
// use Illuminate\Http\Request;
// use Symfony\Component\HttpFoundation\Response;
// use Illuminate\Support\Facades\Auth;

// class RoleMiddleware
// {
//     public function handle(Request $request, Closure $next, $role): Response
//     {

//         \Log::info("Role check for {$role}: " . $request->fullUrl());

//         if (!Auth::check()) {
//             \Log::warning("Unauthorized access attempt to " . $request->fullUrl());
//             return response()->json([
//                 'message' => 'Unauthorized'
//             ], 401);
//         }

//         if (Auth::user()->role->value != $role) {
//             \Log::warning("Access denied for user " . Auth::id() . " (Role: " . Auth::user()->role->value . ") to " . $request->fullUrl());
//             return response()->json([
//                 'message' => 'Access denied'
//             ], 403);
//         }

//         \Log::info("Access granted for user " . Auth::id());
//         return $next($request);
//     }
// }

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Enums\RoleEnum;


class RoleMiddleware
{
    public function handle($request, Closure $next, $role)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $userRole = is_object($user->role) && isset($user->role->value) ? $user->role->value : (string) $user->role;
        $requiredRole = RoleEnum::tryFrom($role);
        $requiredRoleValue = $requiredRole ? $requiredRole->value : $role;

        $isAdmin = $userRole === 'admin' || $userRole === 'ADM' || (method_exists($user, 'isAdmin') && $user->isAdmin());

        if ($userRole !== $requiredRoleValue && !$isAdmin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}