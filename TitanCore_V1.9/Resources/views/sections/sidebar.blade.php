@php
    // Worksuite plugin main sidebar include (tenant)
@endphp

@if(Route::has('titancore.tenant.titanai.launcher'))
<li class="sidebar-item">
    <a class="sidebar-link" href="{{ route('titancore.tenant.titanai.launcher') }}">
        <i class="ti ti-sparkles"></i>
        <span class="hide-menu">Titan Tools</span>
    </a>
</li>
@endif
