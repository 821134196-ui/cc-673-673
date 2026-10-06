<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 本地模拟身份：前端通过 X-Actor-Id 选择当前操作用户（实验员/负责人）。
 */
class ResolveActor
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Actor-Id') ?: $request->cookie('actor_id');
        $user = $id ? User::find($id) : User::query()->orderBy('id')->first();

        if (! $user) {
            abort(401, '未找到操作用户，请先初始化数据');
        }

        $request->setUserResolver(fn () => $user);
        app()->instance(User::class, $user);

        return $next($request);
    }
}
