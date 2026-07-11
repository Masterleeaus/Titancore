@php
    // Worksuite plugin main sidebar include (superadmin)
@endphp

@if(Route::has('titancore.admin.titanai.console'))
<li class="sidebar-item">
    <a class="sidebar-link" href="{{ route('titancore.admin.titanai.console') }}">
        <i class="ti ti-cpu"></i>
        <span class="hide-menu">Titan Core</span>
    </a>
</li>
@endif
