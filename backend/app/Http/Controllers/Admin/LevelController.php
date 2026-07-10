<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IqLevel;

class LevelController extends Controller
{
    public function index()
    {
        return response()->json(['data' => IqLevel::orderBy('level_number')->get()]);
    }
}
