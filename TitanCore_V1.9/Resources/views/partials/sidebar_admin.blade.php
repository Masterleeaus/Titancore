@php
@endphp
@if (Route::has('titancore.admin.titanai.console'))
<li class="sidebar-item">
    <a class="sidebar-link has-arrow" href="#" aria-expanded="false">
        <i class="ti ti-sparkles"></i>
        <span class="hide-menu">Titan AI</span>
    </a>
    <ul class="collapse first-level">
        @if(Route::has('titan.core.ai.settings'))
        <li class="sidebar-item">
            <a class="sidebar-link" href="{{ route('titan.core.ai.settings') }}">
                <i class="ti ti-settings"></i>
                <span class="hide-menu">AI Settings</span>
            </a>
        </li>
        @endif
        @if(Route::has('titan.core.ai.agents.index'))
        <li class="sidebar-item">
            <a class="sidebar-link" href="{{ route('titan.core.ai.agents.index') }}">
                <i class="ti ti-robot"></i>
                <span class="hide-menu">Agent Builder</span>
            </a>
        </li>
        @endif
        @if(Route::has('titancore.prompts.index'))
        <li class="sidebar-item">
            <a class="sidebar-link" href="{{ route('titancore.prompts.index') }}">
                <i class="ti ti-file-text"></i>
                <span class="hide-menu">AI Prompts</span>
            </a>
        </li>
        @endif
        <li class="sidebar-item">
            <a class="sidebar-link" href="{{ route('titancore.admin.titanai.console') }}">
                <i class="ti ti-terminal"></i>
                <span class="hide-menu">Titan AI Console</span>
            </a>
        </li>
    </ul>
</li>
@endif
