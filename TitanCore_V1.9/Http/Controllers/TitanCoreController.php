<?php

namespace Modules\TitanCore\Http\Controllers;

use Illuminate\Routing\Controller;

class TitanCoreController extends Controller
{
    public function index()
    {
        return view('titancore::index');
    }

    public function prompts()
    {
        return view('titancore::prompts');
    }

    public function health()
    {
        return response()->json(['status' => 'ok']);
    }
}
