<?php

namespace Modules\TitanCore\Http\Controllers\Admin;

use Illuminate\Routing\Controller;

class TitanAiConsoleController extends Controller
{
    protected function viewName(): string
    {
        return 'titancore::admin.titanai.console';
    }

    public function index()
    {
        return view($this->viewName());
    }
}
