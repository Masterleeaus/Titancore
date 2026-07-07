@php
// Zero-risk sidebar include: guard route existence
@endphp
@if (Route::has('titancore.tenant.titanai.launcher'))
<li class="sidebar-item">
    <a class="sidebar-link" href="{{ route('titancore.tenant.titanai.launcher') }}">
        <i class="ti ti-sparkles"></i>
        <span class="hide-menu">Titan Tools</span>
    </a>
</li>
@endif
