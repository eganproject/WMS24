<!DOCTYPE html>
<html lang="en">
<!--begin::Head-->
@include('layouts.partials._head')
<!--end::Head-->
<!--begin::Body-->

<body id="kt_body" style="background-image: url(/metronic/assets/media/patterns/header-bg.jpg)"
    class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled">
    <!--begin::Main-->
    <!--begin::Root-->
    <div class="d-flex flex-column flex-root">
        <!--begin::Page-->
        <div class="page d-flex flex-row flex-column-fluid">
            <!--begin::Wrapper-->
            <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
                @include('layouts.partials._header')

                <!--begin::Content-->
                @stack('toolbar')
                
                <div id="kt_content_container" class="d-flex flex-column-fluid align-items-start container-xxl">
                        @yield('content')
                </div>

                @include('layouts.partials._footer')
            </div>
            <!--end::Wrapper-->
        </div>
        <!--end::Page-->
        @include('layouts.partials._warehousename')
    </div>
    <!--end::Root-->
    @include('layouts.partials._scripts')
</body>
<!--end::Body-->

</html>
