<button id="kt_explore_toggle"
    class="explore-toggle btn btn-sm bg-body btn-color-gray-700 btn-active-primary shadow-sm position-fixed px-5 fw-bolder zindex-2 top-50 mt-10 end-0 transform-90 fs-6 rounded-top-0"
    title="" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-trigger="hover"
    data-bs-original-title="{{ auth()->user()->warehouse->name ?? 'Full Akses Gudang' }}">
    <span id="kt_explore_toggle_label">{{ auth()->user()->warehouse->name ?? 'Full Akses Gudang' }}</span>
</button>
