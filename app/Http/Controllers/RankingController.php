<?php

namespace App\Http\Controllers;

use App\Http\Resources\Top100Resource;
use App\Services\RankingService;
use Illuminate\Http\JsonResponse;

class RankingController extends Controller
{
    public function top100(RankingService $service): JsonResponse
    {
        $user = auth()->user();

        $data = $service->getTop100Data($user->id);

        return response()->json(new Top100Resource($data));
    }
}
