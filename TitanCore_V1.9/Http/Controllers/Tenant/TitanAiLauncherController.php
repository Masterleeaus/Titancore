<?php

namespace Modules\TitanCore\Http\Controllers\Tenant;

use Illuminate\Routing\Controller;

class TitanAiLauncherController extends Controller
{
    public function index()
    {
        return view('titancore::tenant.titanai.launcher');
    }
}
