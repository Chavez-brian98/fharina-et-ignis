document.addEventListener('DOMContentLoaded', function () {

    // ==========================================================================
    // TOASTS Toastify (mensajes flash tras crear/editar/desactivar/eliminar)
    // ==========================================================================
    const flashToast = document.getElementById('flashToast');
    if (flashToast && typeof Toastify !== 'undefined') {
        const message = flashToast.getAttribute('data-message') || '';
        const isError = flashToast.getAttribute('data-type') === 'error';
        Toastify({
            text: message,
            duration: 3500,
            gravity: 'top',
            position: 'right',
            close: true,
            stopOnFocus: true,
            style: {
                background: isError ? '#dc2626' : '#16a34a',
                borderRadius: '12px',
                boxShadow: isError
                    ? '0 10px 30px -6px rgba(220, 38, 38, 0.4)'
                    : '0 10px 30px -6px rgba(22, 163, 74, 0.4)',
                fontFamily: 'inherit',
                fontSize: '14px',
                fontWeight: '600'
            },
            offset: { x: 0, y: 20 }
        }).showToast();
    }

    // ==========================================================================
    // SIDEBAR COLAPSABLE (solo iconos al colapsar, estado persistido)
    // ==========================================================================
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        const applyCollapsed = function () {
            document.body.classList.toggle('sidebar-collapsed', localStorage.getItem('sidebar-collapsed') === '1');
        };
        applyCollapsed();
        sidebarToggle.addEventListener('click', function () {
            const collapsed = document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0');
        });
    }

    // ==========================================================================
    // BÚSQUEDA EN TIEMPO REAL + FILTROS (aplica a cualquier CRUD)
    // Las filas usan data-search (texto plano) y atributos data-* de filtro.
    // ==========================================================================
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('crudTableBody');

    function applyFilters() {
        if (!tableBody) return;

        const rows = tableBody.querySelectorAll('tr[data-search]');
        const categoryFilter = document.getElementById('filterCategory');
        const statusFilter = document.getElementById('filterStatus');
        const lowStockFilter = document.getElementById('filterLowStock');

        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const category = categoryFilter ? categoryFilter.value : '';
        const status = statusFilter ? statusFilter.value : '';
        const lowStock = lowStockFilter ? lowStockFilter.checked : false;

        let visible = 0;

        rows.forEach(function (row) {
            const searchText = (row.getAttribute('data-search') || '').toLowerCase();
            const rowCategory = (row.getAttribute('data-category') || '');
            const rowStatus = (row.getAttribute('data-status') || '');
            const rowLow = row.getAttribute('data-lowstock') === '1';

            let show = true;

            if (query && !searchText.includes(query)) show = false;
            if (show && category && rowCategory !== category) show = false;
            if (show && status && rowStatus !== status) show = false;
            if (show && lowStock && !rowLow) show = false;

            row.classList.toggle('hidden', !show);
            if (show) visible++;
        });

        const emptyState = document.getElementById('emptyState');
        if (emptyState) {
            emptyState.classList.toggle('hidden', visible > 0);
        }
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    tableBody && document.querySelectorAll('.search-filter').forEach(function (el) {
        el.addEventListener('change', applyFilters);
    });

    // ==========================================================================
    // MODAL DE DETALLE (fondo con blur)
    // ==========================================================================
    const modal = document.getElementById('detailModal');

    function openModal() {
        if (!modal) return;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (!modal) return;
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }

    document.body.addEventListener('click', function (e) {
        const detailBtn = e.target.closest('.btn-detail');
        if (detailBtn) {
            const raw = detailBtn.getAttribute('data-detail');
            const body = document.getElementById('detailModalBody');
            const titleEl = document.getElementById('detailModalTitle');
            if (body && raw) {
                const data = JSON.parse(raw);
                const title = detailBtn.getAttribute('data-title') || 'Detalle';
                const image = detailBtn.getAttribute('data-image') || '';
                const icon = detailBtn.getAttribute('data-icon') || 'fa-circle-info';

                let mediaHtml = '<div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-orange-100 to-orange-50">';
                if (image) {
                    mediaHtml += '<img src="' + image + '" alt="' + title + '"'
                        + ' class="absolute inset-0 w-full h-full object-cover"'
                        + ' onerror="this.remove()">';
                } else {
                    mediaHtml += '<i class="fa-solid ' + icon + ' text-8xl text-orange-400/50"></i>';
                }
                mediaHtml += '</div>';

                let fields = '';
                for (const key in data) {
                    if (key === 'ID') continue;
                    const rawVal = data[key];
                    const val = (rawVal !== null && rawVal !== '' && rawVal !== undefined) ? rawVal : '—';
                    const isLong = key === 'Nombre' || key === 'Descripción' || String(val).length > 30;
                    if (isLong) {
                        fields += '<div class="sm:col-span-2 rounded-xl bg-white px-4 py-3 ring-1 ring-gray-100">'
                            + '<span class="block text-sm font-medium text-gray-500 mb-1">' + key + '</span>'
                            + '<span class="block text-sm font-semibold text-gray-900 leading-snug">' + val + '</span>'
                            + '</div>';
                    } else {
                        fields += '<div class="flex items-center justify-between gap-3 rounded-xl bg-white px-4 py-3.5 ring-1 ring-gray-100">'
                            + '<span class="text-sm font-medium text-gray-500 shrink-0">' + key + '</span>'
                            + '<span class="text-sm font-semibold text-gray-900 text-right">' + val + '</span>'
                            + '</div>';
                    }
                }

                body.innerHTML = '<div class="flex flex-col sm:flex-row gap-6">'
                    + '<div class="relative shrink-0 w-full sm:w-72 h-56 sm:h-72 rounded-2xl overflow-hidden ring-1 ring-orange-100 shadow-lg shadow-orange-100/60">' + mediaHtml + '</div>'
                    + '<div class="flex-1 grid grid-cols-1 min-[480px]:grid-cols-2 gap-3 content-start">' + fields + '</div>'
                    + '</div>';

                if (titleEl) titleEl.textContent = title;
            }
            openModal();
        }
    });

    document.body.addEventListener('click', function (e) {
        if (e.target.closest('.btn-close-modal') || e.target.classList.contains('modal-overlay') && e.target === modal) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    // ==========================================================================
    // DESACTIVAR / ELIMINAR con confirmación (SweetAlert)
    // ==========================================================================
    document.body.addEventListener('click', function (e) {
        const toggleBtn = e.target.closest('.btn-toggle');
        const deleteBtn = e.target.closest('.btn-delete');

        if (toggleBtn) {
            e.preventDefault();
            const activating = toggleBtn.getAttribute('data-state') === 'inactive';
            Swal.fire({
                title: activating ? '¿Activar?' : '¿Desactivar?',
                text: activating
                    ? 'El registro "' + (toggleBtn.getAttribute('data-name') || '') + '" será activado.'
                    : 'El registro "' + (toggleBtn.getAttribute('data-name') || '') + '" será desactivado.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f97316',
                cancelButtonColor: '#6b7280',
                confirmButtonText: activating ? 'Sí, activar' : 'Sí, desactivar',
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (result.isConfirmed) {
                    window.location.href = toggleBtn.getAttribute('data-url');
                }
            });
            return;
        }

        if (deleteBtn) {
            e.preventDefault();
            Swal.fire({
                title: '¿Eliminar?',
                text: 'El registro "' + (deleteBtn.getAttribute('data-name') || '') + '" será eliminado definitivamente. Esta acción no se puede deshacer.',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then(function (result) {
                if (result.isConfirmed) {
                    window.location.href = deleteBtn.getAttribute('data-url');
                }
            });
        }
    });
});