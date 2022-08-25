<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GeneratorController extends Controller
{
    public function index()
    {
        return inertia('generator');
    }
}
