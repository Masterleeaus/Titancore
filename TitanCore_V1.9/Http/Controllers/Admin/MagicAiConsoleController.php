<?php

namespace Modules\TitanCore\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MagicAiConsoleController extends Controller
{
    public function index()
    {
        // Simple view-only console; calls TitanCore API endpoints from the browser
        return view('titancore::admin.magicai.console');
    }
}
