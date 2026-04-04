   <div class="toolbar py-5 py-lg-15" id="kt_toolbar">
       <!--begin::Container-->
       <div id="kt_toolbar_container" class="container-xxl d-flex flex-stack flex-wrap">
           <!--begin::Page title-->
           <div class="page-title d-flex flex-column me-3">
               <!--begin::Title-->
               <h1 class="d-flex text-white fw-bolder my-1 fs-3">{{ $title }}</h1>
               <!--end::Title-->
               <!--begin::Breadcrumb-->
               <ul class="breadcrumb breadcrumb-separatorless fw-bold fs-7 my-1">

                   @foreach ($breadcrumbs as $i => $value)
                       @if ($i > 0)
                           <li class="breadcrumb-item">
                               <span class="bullet bg-white opacity-75 w-5px h-2px"></span>
                           </li>
                       @endif
                       <li class="breadcrumb-item text-white opacity-75">
                           <a href="#" class="text-white text-hover-primary">{{ $value }}</a>
                       </li>
                   @endforeach
                  
               </ul>
               <!--end::Breadcrumb-->
           </div>
           <!--end::Page title-->
       </div>
       <!--end::Container-->
   </div>
