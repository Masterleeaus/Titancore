<?php

namespace Modules\TitanCore\Http\Controllers\Tenant;

class MagicAiLauncherController extends TitanAiLauncherController
{
    protected function viewName(): string
    {
        return 'titancore::tenant.magicai.launcher';
    }
}
