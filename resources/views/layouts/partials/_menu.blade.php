@foreach ($menuTree as $menu)
    @include('layouts.partials._menu_sub', ['menu' => $menu, 'level' => 0])
@endforeach
