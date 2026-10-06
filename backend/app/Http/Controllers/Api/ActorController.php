<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class ActorController extends Controller
{
    /** 当前可选操作员（本地模拟登录）。 */
    public function index()
    {
        return response()->json(['data' => User::orderBy('id')->get(['id', 'name', 'role'])]);
    }
}
