@if (empty($menu->children))
    {{-- Leaf node (link) --}}
    <div class="menu-item @if($level == 0) me-lg-1 @endif">
        <a class="menu-link py-3" href="{{ $menu->url }}">
            @if($menu->icon && $level > 0)
                <span class="menu-icon"><i class="{{ $menu->icon }} fs-6"></i></span>
            @elseif($level > 0)
                <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
            @endif
            <span class="menu-title">{{ $menu->name }}</span>
        </a>
    </div>
@else
    {{-- Parent node (dropdown) --}}
    <div data-kt-menu-trigger="click" data-kt-menu-placement="bottom-start" class="menu-item menu-lg-down-accordion @if($level == 0) me-lg-1 @endif">
        <span class="menu-link py-3">
            @if($menu->icon && $level > 0)
                <span class="menu-icon"><i class="{{ $menu->icon }} fs-5"></i></span>
            @elseif($level > 0)
                <span class="menu-bullet"><span class="bullet bullet-dot"></span></span>
            @endif
            <span class="menu-title">{{ $menu->name }}</span>
            <span class="menu-arrow @if($level == 0) d-lg-none @endif"></span>
        </span>
        <div class="menu-sub menu-sub-lg-down-accordion menu-sub-lg-dropdown menu-rounded-0 py-lg-4 w-lg-225px">
            @foreach ($menu->children as $child)
                @include('layouts.partials._menu_sub', ['menu' => $child, 'level' => $level + 1])
            @endforeach
        </div>
    </div>
@endif
