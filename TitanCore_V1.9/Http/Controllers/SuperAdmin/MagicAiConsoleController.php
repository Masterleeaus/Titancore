<?php

namespace Modules\TitanCore\Http\Controllers\SuperAdmin;

use Illuminate\Routing\Controller;

class MagicAiConsoleController extends Controller
{
    public function index()
    {
        // SuperAdmin layout contract:
        // resources/views/super-admin/sections/topbar.blade.php requires these variables.
        $pageTitle = 'Titan Core';
        $checkListCompleted = 0;
        $checkListTotal = 0;
        $unreadNotificationCount = 0;
        $unreadNotifications = [];

        return view('titancore::super-admin.magicai.console', compact(
            'pageTitle',
            'checkListCompleted',
            'checkListTotal',
            'unreadNotificationCount',
            'unreadNotifications'
        ));
    }
}
