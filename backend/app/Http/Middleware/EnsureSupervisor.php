<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupervisor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || $user->role !== \App\Models\User::ROLE_SUPERVISOR) {
            return response()->json(['message' => '仅负责人可执行此操作'], 403);
        }

        return $next($request);
    }
}
