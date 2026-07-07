@php
    $titancoreCanView   = user()->can('titancore.view');
    $titancoreCanManage = user()->can('titancore.manage');
@endphp

@if ($titancoreCanView || $titancoreCanManage)
    <li class="nav-item {{ request()->routeIs('titancore.*') ? 'active' : '' }}">
        <a class="nav-link" href="javascript:;">
            <i class="fa fa-brain"></i>
            <span class="title">Titan Core (AI Engine)</span>
            <span class="arrow {{ request()->routeIs('titancore.*') ? 'open' : '' }}"></span>
        </a>
        <ul class="sub-menu">
            @if ($titancoreCanManage)
                <li class="nav-item {{ request()->routeIs('titancore.dashboard.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('titancore.dashboard.index') }}">
                        <span class="title">AI Dashboard</span>
                    </a>
                </li>
                <li class="nav-item {{ request()->routeIs('titancore.settings.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('titancore.settings.index') }}">
                        <span class="title">AI Settings</span>
                    </a>
                </li>
                <li class="nav-item {{ request()->routeIs('titancore.usage.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('titancore.usage.index') }}">
                        <span class="title">Usage</span>
                    </a>
                </li>
            @endif

            <li class="nav-item {{ request()->routeIs('titancore.prompts.*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('titancore.prompts.index') }}">
                    <span class="title">Prompts</span>
                </a>
            </li>

            <li class="nav-item {{ request()->routeIs('titancore.health') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('titancore.health') }}">
                    <span class="title">Health</span>
                </a>
            </li>
        </ul>
    </li>
@endif
