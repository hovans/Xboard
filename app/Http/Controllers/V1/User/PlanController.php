<?php

namespace App\Http\Controllers\V1\User;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    public function fetch(Request $request)
    {
    	   
         try {
            // 获取当前登录的用户
            $user = User::find($request->user['id']);
            // 检查用户是否登录
            if (!$user) {
                Log::info('Unauthorized access attempt');
                return $this->fail([401, __('User not authenticated')]);
            }

            // 检查用户是否有权限组
            if (!$user->group_id) {
                // 分配一个默认的权限组
                $user->group_id = 1;
                $user->save();
            }

            // 检查是否传递了套餐的 id 参数
            if ($request->input('id')) {
                $plan = Plan::where('id', $request->input('id'))->first();
                if (!$plan) {
                    Log::warning('Plan not found', ['plan_id' => $request->input('id')]);
                    return $this->fail([400, __('Subscription plan does not exist')]);
                }
                if ((!$plan->show && !$plan->renew) || (!$plan->show && $user->plan_id !== $plan->id)) {
                    Log::warning('Access to hidden or non-renewable plan denied', [
                        'plan_id' => $plan->id,
                        'user_id' => $user->id
                    ]);
                    return $this->fail([400, __('Subscription plan does not exist')]);
                }
                return $this->success($plan);
            }

            // 获取用户权限组的所有套餐，不论是否显示
            $plans = Plan::where('group_id', $user->group_id)
                ->orderBy('sort', 'ASC')
                ->get();

            // 处理套餐容量限制
            $counts = PlanService::countActiveUsers();
            foreach ($plans as $k => $v) {
                if ($plans[$k]->capacity_limit === NULL) continue;
                if (!isset($counts[$plans[$k]->id])) continue;
                $plans[$k]->capacity_limit = $plans[$k]->capacity_limit - $counts[$plans[$k]->id]->count;
            }

            return $this->success($plans);

        } catch (\Exception $e) {
            // 记录异常信息
            Log::error('Error in PlanController@fetch', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request' => $request->all()
            ]);
            // 返回500错误码
            return response()->json(['error' => __('Internal Server Error')], 500);
        }
//        $user = User::find($request->user['id']);
//        if ($request->input('id')) {
//            $plan = Plan::where('id', $request->input('id'))->first();
//            if (!$plan) {
//                return $this->fail([400, __('Subscription plan does not exist')]);
//            }
//            if ((!$plan->show && !$plan->renew) || (!$plan->show && $user->plan_id !== $plan->id)) {
//                return $this->fail([400, __('Subscription plan does not exist')]);
//            }
//            return $this->success($plan);
//        }
//
//        $counts = PlanService::countActiveUsers();
//        $plans = Plan::where('show', 1)
//            ->orderBy('sort', 'ASC')
//            ->get();
//        foreach ($plans as $k => $v) {
//            if ($plans[$k]->capacity_limit === NULL) continue;
//            if (!isset($counts[$plans[$k]->id])) continue;
//            $plans[$k]->capacity_limit = $plans[$k]->capacity_limit - $counts[$plans[$k]->id]->count;
//        }
//        return $this->success($plans);
    }
}
