<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCheckMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
		/* $isAdminRequest = $request->header('Admin') === 'true';
        if ($isAdminRequest) {
            $adminToken = $request->header('Token');
            if (!$adminToken) {
                return response()->json(['result' => -3, 'message' => 'Unauthorized. Admin token required.'], 403);
            }
            $admin = DB::table('admins')
                ->where('admin_token', $adminToken)
                ->where('status', 'Active')
                ->first();
            if (!$admin) {
                return response()->json(['result' => -3, 'message' => 'Unauthorized. Invalid token or inactive account.'], 403);
            }
        } */
        return $next($request);
    }
}
