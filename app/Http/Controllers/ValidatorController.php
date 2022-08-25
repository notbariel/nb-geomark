<?php

namespace App\Http\Controllers;

use App\Services\GeoValidatorService;
use Illuminate\Http\Request;

class ValidatorController extends Controller
{
    public function index(Request $request, GeoValidatorService $validator)
    {
        if ($request->has('url') && $request->get('url')) {
            $results = $validator
                ->validate($request->get('url'))
                ->getResults();
        }

        return inertia('validator', [
            'url' => $request->get('url') ?? null,
            'results' => $results ?? null,
        ]);
    }
}
