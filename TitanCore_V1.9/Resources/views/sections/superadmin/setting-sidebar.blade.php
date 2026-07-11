@php
    // Worksuite plugin superadmin settings sidebar include
@endphp

@if(Route::has('titancore.admin.titanai.console'))
    <x-setting-menu-item :active="$activeMenu ?? ''"
                         menu="titancore_titanai"
                         :href="route('titancore.admin.titanai.console')"
                         :text="__('Titan Core')" />
@endif
