<?php

namespace Modules\TitanCore\Http\Controllers\Tenant;

use Illuminate\Routing\Controller;

class MagicAiLauncherController extends Controller
{
    public function index()
    {
        return view('titancore::tenant.magicai.launcher');
    }
}
