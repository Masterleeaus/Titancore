<?php

namespace Modules\TitanCore\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Modules\TitanCore\Http\Controllers\Api\Concerns\ProxiesAiRequests;

/**
 * MagicAI Proxy Controller
 *
 * Exposes MagicAI endpoints through TitanCore so Worksuite can use all MagicAI features.
 * This is a permission-gated passthrough; actual proxy logic lives in ProxiesAiRequests.
 *
 * Extends the same passthrough mechanism as TitanAiProxyController; authorization
 * differences are enforced at the route middleware level, not in this controller.
 */
class MagicAiProxyController extends Controller
{
    use ProxiesAiRequests;
}
