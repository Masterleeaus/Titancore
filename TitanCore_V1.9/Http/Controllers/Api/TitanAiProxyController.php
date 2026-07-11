<?php

namespace Modules\TitanCore\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Modules\TitanCore\Http\Controllers\Api\Concerns\ProxiesAiRequests;

/**
 * TitanAI Proxy Controller
 *
 * Exposes titanai endpoints through TitanCore so Worksuite can use all titanai features.
 * This is a permission-gated passthrough; actual proxy logic lives in ProxiesAiRequests.
 */
class TitanAiProxyController extends Controller
{
    use ProxiesAiRequests;
}
