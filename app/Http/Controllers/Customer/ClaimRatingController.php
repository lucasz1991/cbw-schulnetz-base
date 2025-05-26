<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


use App\Jobs\ClaimRatingAIEval;
use App\Models\ClaimRating;


class ClaimRatingController extends Controller
{
    static function evaluateScore($claimRating)
    {
        ClaimRatingAIEval::dispatch($claimRating);
        return response()->json([
            'success' => true,
        ]);
    }
}
