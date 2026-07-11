<?php

namespace Modules\TitanCore\Http\Controllers\Tenant;

use Illuminate\Routing\Controller;

class TitanAiLauncherController extends Controller
{
    protected function viewName(): string
    {
        return 'titancore::tenant.titanai.launcher';
    }

    public function index()
    {
        return view($this->viewName());
    }
}
