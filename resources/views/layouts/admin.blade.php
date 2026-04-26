<!DOCTYPE html>
<html lang="en">

<head>
    <base href="">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Import Analytics')</title>
    <link rel="shortcut icon" href="{{ asset('metronic/media/logos/favicon.png') }}" />
    <link href="{{ asset('metronic/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('metronic/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('metronic/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
    <style>
        body {
            font-family: "Poppins", "Inter", "Segoe UI", Arial, sans-serif;
        }

        .select2-container .select2-selection--single {
            height: 42px;
            padding: 0.5rem 1rem;
            border-radius: 0.475rem;
            border: 1px solid #e4e6ef;
        }

        .form-select.form-select-solid+.select2-container .select2-selection--single {
            background-color: #f5f8fa;
            border-color: #f5f8fa;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 30px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            right: 12px;
        }

        .select2-container .select2-selection--multiple {
            min-height: 42px;
            border-radius: 0.475rem;
            border: 1px solid #e4e6ef;
        }

        .select2-container--open {
            z-index: 2000;
        }

        .flatpickr-calendar {
            z-index: 2000 !important;
        }

        .modal .select2-container--open,
        .modal .flatpickr-calendar {
            z-index: 2065 !important;
        }

        .modal .select2-dropdown {
            z-index: 2065 !important;
        }

        .table-search-toolbar {
            width: min(100%, 430px);
            display: flex;
            align-items: center;
            gap: 0.35rem;
            flex-wrap: nowrap;
            padding: 0.35rem;
            border: 1px solid #e4e6ef;
            border-radius: 0.75rem;
            background: #f5f8fa;
        }

        .table-search-toolbar .table-search-input {
            flex: 1 1 auto;
            min-width: 0;
            width: auto !important;
            min-height: 42px;
            border-radius: 0.55rem !important;
            border: 0 !important;
            background: transparent !important;
            color: #181c32;
            box-shadow: none !important;
        }

        .table-search-toolbar .table-search-input:focus {
            background: #fff !important;
            box-shadow: 0 0 0 0.2rem rgba(0, 158, 247, 0.08) !important;
        }

        .table-search-toolbar .position-absolute.ms-6 {
            opacity: 0.6;
            pointer-events: none;
        }

        .table-search-mode-wrap {
            display: inline-flex;
            align-items: center;
            flex: 0 0 auto;
            gap: 0.25rem;
            min-height: 38px;
            padding: 0.25rem;
            border: 1px solid #e4e6ef;
            border-radius: 0.6rem;
            background: #fff;
        }

        .table-search-mode-label {
            width: 1px;
            height: 1px;
            overflow: hidden;
            position: absolute;
            clip: rect(0, 0, 0, 0);
        }

        .table-search-mode-select {
            position: absolute;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        .table-search-mode-select:focus,
        .table-search-mode-select:focus-visible {
            border: 0;
            box-shadow: none !important;
        }

        .table-search-mode-button {
            width: 34px;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 0.45rem;
            background: transparent;
            color: #7e8299;
            transition: color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
        }

        .table-search-mode-button:hover,
        .table-search-mode-button:focus-visible {
            color: #009ef7;
            background: #eef6ff;
        }

        .table-search-mode-button.is-active {
            color: #fff;
            background: #009ef7;
            box-shadow: 0 4px 10px rgba(0, 158, 247, 0.2);
        }

        .table-search-card-header {
            row-gap: 1rem;
        }

        .table-search-card-header .card-title {
            flex: 1 1 340px;
            min-width: 0;
        }

        .table-search-card-header .card-toolbar {
            flex: 1 1 auto;
            min-width: 0;
        }

        @media (max-width: 767.98px) {
            .table-search-card-header {
                align-items: stretch !important;
            }

            .table-search-card-header .card-title,
            .table-search-card-header .card-toolbar {
                width: 100%;
                margin: 0;
            }

            .table-search-card-header .card-toolbar {
                justify-content: flex-start;
            }

            .table-search-card-header .card-toolbar > .d-flex {
                width: 100%;
                flex-wrap: wrap !important;
                gap: 0.75rem !important;
            }

            .table-search-card-header .card-toolbar [class*="min-w-"] {
                min-width: 0 !important;
                width: 100%;
            }

            .table-search-card-header .card-toolbar .form-control,
            .table-search-card-header .card-toolbar .form-select {
                width: 100% !important;
            }

            .table-search-toolbar {
                width: 100%;
            }

            .table-search-toolbar .table-search-input {
                width: 100% !important;
                min-width: 0;
                flex-basis: auto;
            }

            .table-search-mode-wrap {
                margin-left: auto;
            }
        }

        @media (max-width: 575.98px) {
            .table-search-card-header .btn {
                width: 100%;
            }
        }
    </style>
    @stack('styles')
    @yield('styles')
    <meta name="description" content="Import Analytics dashboard" />
    <meta name="keywords" content="import analytics,dashboard,analytics" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
</head>

<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled">
    <div class="d-flex flex-column flex-root">
        <!--begin::Page-->
        <div class="page d-flex flex-row flex-column-fluid">
            <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
                @include('layouts.partials.header')
                @include('layouts.partials.toolbar')

                {{-- <div class="toolbar py-5 py-lg-5" id="kt_toolbar">
                    <div id="kt_toolbar_container" class="container-xxl d-flex flex-stack flex-wrap">
                        <div class="page-title d-flex flex-column me-3">
                            <h1 class="d-flex text-dark fw-bolder my-1 fs-3">@yield('page_title', 'Dashboard')</h1>
                            <ul class="breadcrumb breadcrumb-dot fw-bold text-gray-600 fs-7 my-1">
                                @yield('page_breadcrumbs')
                            </ul>
                        </div>
                        <div class="d-flex align-items-center gap-2 my-1">
                            @yield('page_actions')
                        </div>
                    </div>
                </div> --}}

                <div id="kt_content_container" class="d-flex flex-column-fluid align-items-start container-xxl">
                    <!--begin::Post-->
                    <div class="content flex-row-fluid" id="kt_content">
                        @yield('content')

                    </div>
                </div>

                @include('layouts.partials.footer')
            </div>
        </div>
    </div>

    <script src="{{ asset('metronic/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('metronic/js/scripts.bundle.js') }}"></script>
    <script src="{{ asset('metronic/plugins/custom/datatables/datatables.bundle.js') }}"></script>
    <script>
        (function () {
            const searchInputSelectors = [
                'input[data-kt-filter="search"]',
                'input#report_search',
                'input#filter_search',
            ].join(', ');

            const findSearchModeControl = (root = document) => {
                if (!root || typeof root.querySelector !== 'function') {
                    return null;
                }

                return root.querySelector('[data-search-mode-control]');
            };

            const reloadAllDataTables = () => {
                if (typeof window.jQuery === 'undefined' || !window.jQuery.fn?.dataTable) {
                    return;
                }

                window.jQuery.fn.dataTable.tables({ api: true }).every(function () {
                    if (this.ajax && typeof this.ajax.reload === 'function') {
                        this.ajax.reload();
                    }
                });
            };

            const attachSearchModeControls = () => {
                document.querySelectorAll(searchInputSelectors).forEach((input) => {
                    if (!input || input.dataset.searchModeReady === '1') {
                        return;
                    }

                    const host = input.closest('.d-flex.align-items-center') || input.parentElement;
                    if (!host || findSearchModeControl(host)) {
                        input.dataset.searchModeReady = '1';
                        return;
                    }

                    const cardHeader = host.closest('.card-header');
                    const cardTitle = host.closest('.card-title');
                    if (cardHeader) {
                        cardHeader.classList.add('table-search-card-header');
                    }
                    if (cardTitle) {
                        cardTitle.classList.add('w-100');
                    }

                    host.classList.add('table-search-toolbar');
                    input.classList.add('table-search-input');

                    const modeWrap = document.createElement('div');
                    modeWrap.className = 'table-search-mode-wrap';
                    const modeLabel = document.createElement('span');
                    modeLabel.className = 'table-search-mode-label';
                    modeLabel.textContent = 'Mode pencarian';

                    const select = document.createElement('select');
                    select.className = 'table-search-mode-select';
                    select.setAttribute('aria-label', 'Mode pencarian tabel');
                    select.setAttribute('data-search-mode-control', '1');
                    select.innerHTML = `
                        <option value="contains" selected>Kemiripan</option>
                        <option value="exact">Presisi</option>
                    `;

                    const modeOptions = [
                        { value: 'contains', icon: 'fas fa-search', label: 'Cari berdasarkan kemiripan' },
                        { value: 'exact', icon: 'fas fa-bullseye', label: 'Cari presisi' },
                    ];

                    const updateModeButtons = () => {
                        modeWrap.querySelectorAll('[data-search-mode-value]').forEach((button) => {
                            const isActive = button.dataset.searchModeValue === select.value;
                            button.classList.toggle('is-active', isActive);
                            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                        });
                    };

                    const buttons = modeOptions.map((option) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'table-search-mode-button';
                        button.dataset.searchModeValue = option.value;
                        button.setAttribute('aria-label', option.label);
                        button.setAttribute('title', option.label);
                        button.innerHTML = `<i class="${option.icon}" aria-hidden="true"></i>`;
                        button.addEventListener('click', () => {
                            if (select.value === option.value) {
                                return;
                            }

                            select.value = option.value;
                            updateModeButtons();
                            select.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                        return button;
                    });

                    select.addEventListener('change', () => {
                        updateModeButtons();
                        reloadAllDataTables();
                    });
                    modeWrap.append(modeLabel, select, ...buttons);
                    host.appendChild(modeWrap);
                    updateModeButtons();

                    input.dataset.searchModeReady = '1';
                });
            };

            window.resolveTableSearchMode = function (element = null) {
                const scope = element?.closest?.('.content, .card, .modal-content, body') || document;
                const scopedControl = findSearchModeControl(scope);
                if (scopedControl) {
                    return scopedControl.value || 'contains';
                }

                return document.querySelector('[data-search-mode-control]')?.value || 'contains';
            };

            document.addEventListener('DOMContentLoaded', attachSearchModeControls);
            document.addEventListener('shown.bs.modal', attachSearchModeControls);

            if (typeof window.jQuery !== 'undefined') {
                window.jQuery(document).on('preXhr.dt', function (e, settings, data) {
                    data.search_mode = window.resolveTableSearchMode(settings?.nTable || null);
                });
            }
        })();

        (function () {
            const modalRefreshRaf = new WeakMap();

            const getClosestModal = (element) => {
                if (!element || typeof element.closest !== 'function') {
                    return null;
                }
                return element.closest('.modal');
            };

            const getModalFloatingParent = (element) => {
                const modalEl = getClosestModal(element);
                if (!modalEl) {
                    return null;
                }

                return modalEl.querySelector('.modal-content')
                    || modalEl.querySelector('.modal-dialog')
                    || modalEl;
            };

            const queueModalFloatingRefresh = (modalEl) => {
                if (!modalEl || typeof window.requestAnimationFrame !== 'function') {
                    return;
                }

                const existing = modalRefreshRaf.get(modalEl);
                if (existing) {
                    return;
                }

                const rafId = window.requestAnimationFrame(() => {
                    modalRefreshRaf.delete(modalEl);

                    modalEl.querySelectorAll('input, textarea, select').forEach((field) => {
                        const fp = field?._flatpickr;
                        if (!fp || !fp.isOpen || typeof fp._positionCalendar !== 'function') {
                            return;
                        }

                        try {
                            fp._positionCalendar();
                        } catch (error) {
                            // ignore flatpickr internal positioning errors
                        }
                    });

                    if (typeof window.jQuery !== 'undefined' && window.jQuery.fn?.select2) {
                        window.jQuery(modalEl)
                            .find('select.select2-hidden-accessible')
                            .each(function () {
                                const instance = window.jQuery(this).data('select2');
                                const container = instance?.$container;
                                const isOpen = !!container && container.hasClass('select2-container--open');
                                if (!isOpen) {
                                    return;
                                }

                                try {
                                    instance.dropdown?._positionDropdown?.();
                                    instance.dropdown?._resizeDropdown?.();
                                } catch (error) {
                                    // ignore select2 internal positioning errors
                                }
                            });
                    }
                });

                modalRefreshRaf.set(modalEl, rafId);
            };

            const patchSelect2ForModals = () => {
                if (typeof window.jQuery === 'undefined' || !window.jQuery.fn?.select2 || window.__modalSelect2Patched) {
                    return;
                }

                const originalSelect2 = window.jQuery.fn.select2;

                const buildOptions = (element, options) => {
                    if (options == null) {
                        options = {};
                    }

                    if (typeof options !== 'object' || Array.isArray(options) || options.dropdownParent) {
                        return options;
                    }

                    const floatingParent = getModalFloatingParent(element);
                    if (!floatingParent) {
                        return options;
                    }

                    return {
                        ...options,
                        dropdownParent: window.jQuery(floatingParent),
                    };
                };

                const wrappedSelect2 = function (options, ...rest) {
                    if (typeof options === 'string') {
                        return originalSelect2.call(this, options, ...rest);
                    }

                    if (this.length <= 1) {
                        return originalSelect2.call(this, buildOptions(this[0], options), ...rest);
                    }

                    this.each(function () {
                        originalSelect2.call(window.jQuery(this), buildOptions(this, options), ...rest);
                    });

                    return this;
                };

                Object.keys(originalSelect2).forEach((key) => {
                    wrappedSelect2[key] = originalSelect2[key];
                });
                wrappedSelect2.amd = originalSelect2.amd;
                wrappedSelect2.defaults = originalSelect2.defaults;

                window.jQuery.fn.select2 = wrappedSelect2;
                window.__modalSelect2Patched = true;
            };

            const patchFlatpickrForModals = () => {
                if (typeof window.flatpickr !== 'function' || window.__modalFlatpickrPatched) {
                    return;
                }

                const originalFlatpickr = window.flatpickr;

                const buildConfig = (element, config) => {
                    if (!element || typeof config !== 'object' || Array.isArray(config)) {
                        return config;
                    }

                    const floatingParent = getModalFloatingParent(element);
                    if (!floatingParent) {
                        return config;
                    }

                    return {
                        ...config,
                        appendTo: config.appendTo || floatingParent,
                        positionElement: config.positionElement || element,
                    };
                };

                const wrappedFlatpickr = function (selector, config) {
                    if (selector instanceof Element) {
                        return originalFlatpickr(selector, buildConfig(selector, config ?? {}));
                    }

                    if (selector instanceof NodeList || Array.isArray(selector)) {
                        const elements = Array.from(selector);
                        return originalFlatpickr(elements, buildConfig(elements[0], config ?? {}));
                    }

                    if (typeof selector === 'string') {
                        const elements = document.querySelectorAll(selector);
                        return originalFlatpickr(selector, buildConfig(elements[0], config ?? {}));
                    }

                    return originalFlatpickr(selector, config);
                };

                Object.keys(originalFlatpickr).forEach((key) => {
                    wrappedFlatpickr[key] = originalFlatpickr[key];
                });
                wrappedFlatpickr.l10ns = originalFlatpickr.l10ns;
                wrappedFlatpickr.localize = originalFlatpickr.localize;
                wrappedFlatpickr.parseDate = originalFlatpickr.parseDate;
                wrappedFlatpickr.formatDate = originalFlatpickr.formatDate;

                window.flatpickr = wrappedFlatpickr;
                window.__modalFlatpickrPatched = true;
            };

            const applyModalStaticBackdrop = (modalEl) => {
                if (!modalEl || !modalEl.setAttribute) return;
                modalEl.setAttribute('data-bs-backdrop', 'static');
            };

            const enforceModalBackdrops = () => {
                document.querySelectorAll('.modal').forEach(applyModalStaticBackdrop);
            };

            const enforceSweetalertBackdrop = () => {
                if (!window.Swal || window.__swalNoOutsideClickApplied) {
                    return;
                }
                const originalFire = window.Swal.fire.bind(window.Swal);
                window.Swal.fire = function (...args) {
                    if (args.length === 1 && args[0] && typeof args[0] === 'object' && !Array.isArray(args[0])) {
                        const options = { ...args[0], allowOutsideClick: false };
                        return originalFire(options);
                    }
                    const options = {
                        title: args[0],
                        text: args[1],
                        icon: args[2],
                        allowOutsideClick: false,
                    };
                    return originalFire(options);
                };
                window.__swalNoOutsideClickApplied = true;
            };

            enforceModalBackdrops();
            enforceSweetalertBackdrop();
            patchSelect2ForModals();
            patchFlatpickrForModals();

            if (!window.AppSwal) {
                window.AppSwal = {
                    confirm: (title, options = {}) => {
                        if (!window.Swal) {
                            return Promise.resolve(window.confirm(title));
                        }
                        const confirmButtonText = options.confirmButtonText || 'Ya';
                        const cancelButtonText = options.cancelButtonText || 'Batal';
                        const confirmButtonType = options.confirmButtonType || 'primary';
                        return window.Swal.fire({
                            title,
                            icon: options.icon || 'warning',
                            showCancelButton: true,
                            confirmButtonText,
                            cancelButtonText,
                            buttonsStyling: false,
                            customClass: {
                                confirmButton: `btn btn-${confirmButtonType}`,
                                cancelButton: 'btn btn-light',
                            },
                        }).then((result) => result.isConfirmed === true);
                    },
                    error: (message, title = 'Error') => {
                        if (window.Swal) {
                            return window.Swal.fire(title, message || 'Terjadi kesalahan', 'error');
                        }
                        alert(message || 'Terjadi kesalahan');
                    },
                };
            }

            document.addEventListener('DOMContentLoaded', () => {
                enforceModalBackdrops();
                patchSelect2ForModals();
                patchFlatpickrForModals();

                document.addEventListener('shown.bs.modal', (event) => {
                    queueModalFloatingRefresh(event.target);
                });

                document.addEventListener('scroll', (event) => {
                    const modalEl = getClosestModal(event.target);
                    if (!modalEl) {
                        return;
                    }

                    queueModalFloatingRefresh(modalEl);
                }, true);

                window.addEventListener('resize', () => {
                    document.querySelectorAll('.modal.show').forEach(queueModalFloatingRefresh);
                });

                if (document.body && !window.__modalBackdropObserver) {
                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            mutation.addedNodes.forEach((node) => {
                                if (!node || node.nodeType !== 1) return;
                                if (node.classList?.contains('modal')) {
                                    applyModalStaticBackdrop(node);
                                    queueModalFloatingRefresh(node);
                                }
                                node.querySelectorAll?.('.modal')?.forEach((modalEl) => {
                                    applyModalStaticBackdrop(modalEl);
                                    queueModalFloatingRefresh(modalEl);
                                });
                            });
                        });
                    });
                    observer.observe(document.body, { childList: true, subtree: true });
                    window.__modalBackdropObserver = observer;
                }
            });
        })();
    </script>
    @stack('scripts')
    @yield('scripts')
</body>

</html>
