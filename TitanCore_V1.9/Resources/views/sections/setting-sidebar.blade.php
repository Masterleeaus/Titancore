@php
    // Worksuite plugin settings sidebar include
@endphp

@if(Route::has('titancore.tenant.titanai.launcher'))
    <x-setting-menu-item :active="$activeMenu ?? ''"
                         menu="titancore_titanai"
                         :href="route('titancore.tenant.titanai.launcher')"
                         :text="__('Titan Tools')" />
@endif
