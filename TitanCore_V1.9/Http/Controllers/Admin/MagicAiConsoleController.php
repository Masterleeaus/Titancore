<?php

namespace Modules\TitanCore\Http\Controllers\Admin;

class MagicAiConsoleController extends TitanAiConsoleController
{
    protected function viewName(): string
    {
        return 'titancore::admin.magicai.console';
    }
}
