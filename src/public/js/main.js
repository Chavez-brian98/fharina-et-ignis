document.addEventListener('DOMContentLoaded', function () {

    // Color primario activo (definido por el tema dinámico en :root)
    function primaryColor() {
        var c = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim();
        return c || '#f97316';
    }

    // ==========================================================================
    // TOASTS Toastify (mensajes flash tras crear/editar/desactivar/eliminar)
    // ==========================================================================
    // Puede haber mas de un marcador (p. ej. success + error pendientes a la vez)
    document.querySelectorAll('.flashToast').forEach(function (flashToast, i) {
        if (typeof Toastify === 'undefined') {
            return;
        }
        const message = flashToast.getAttribute('data-message') || '';
        // success, error, info (azul) y warning (ambar). Los sobrantes usan el
        // verde de exito para que ningun flash se muestre neutro.
        const palette = {
            error: '#dc2626',
            info: '#2563eb',
            warning: '#d97706'
        };
        const type = flashToast.getAttribute('data-type');
        const background = palette[type] || '#16a34a';
        const r = parseInt(background.slice(1, 3), 16);
        const g = parseInt(background.slice(3, 5), 16);
        const b = parseInt(background.slice(5, 7), 16);
        Toastify({
            text: message,
            duration: 3500,
            gravity: 'top',
            position: 'right',
            close: true,
            stopOnFocus: true,
            style: {
                background: background,
                borderRadius: '12px',
                boxShadow: '0 10px 30px -6px rgba(' + r + ', ' + g + ', ' + b + ', 0.4)',
                fontFamily: 'inherit',
                fontSize: '14px',
                fontWeight: '600'
            },
            offset: { x: 0, y: 20 + (i * 60) }
        }).showToast();
    });

    // ==========================================================================
    // SIDEBAR RESPONSIVE
    // Escritorio (>=1024px): colapsable inline (solo iconos), estado persistido.
    // Móvil/tablet (<1024px): drawer deslizable con fondo oscuro (#sidebarBackdrop).
    // ==========================================================================
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOpenBtn = document.getElementById('sidebarOpenBtn');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    const isDesktop = function () {
        return window.matchMedia('(min-width: 1024px)').matches;
    };

    const openDrawer = function () {
        document.body.classList.add('sidebar-open');
        document.body.style.overflow = 'hidden';
    };
    const closeDrawer = function () {
        document.body.classList.remove('sidebar-open');
        document.body.style.overflow = '';
    };

    // Al cargar: restaurar estado colapsado SOLO en escritorio.
    if (isDesktop() && localStorage.getItem('sidebar-collapsed') === '1') {
        document.body.classList.add('sidebar-collapsed');
    }

    // Botón interno del sidebar: en escritorio colapsa; en móvil cierra el drawer.
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            if (isDesktop()) {
                const collapsed = document.body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebar-collapsed', collapsed ? '1' : '0');
            } else {
                closeDrawer();
            }
        });
    }

    // Escritorio colapsado: al pasar el cursor sobre el logo aparece el botón de expandir
    // (CSS); un clic sobre él expande y todo vuelve a la normalidad.
    const brandRow = document.querySelector('.sidebar .brand-row');
    if (brandRow) {
        brandRow.addEventListener('click', function () {
            if (!isDesktop() || !document.body.classList.contains('sidebar-collapsed')) return;
            document.body.classList.remove('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', '0');
        });
    }

    // Botón flotante (móvil/tablet): abre y cierra el drawer.
    if (sidebarOpenBtn) {
        sidebarOpenBtn.addEventListener('click', function () {
            if (document.body.classList.contains('sidebar-open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
    }

    // Clic en el fondo oscuro: cierra el drawer.
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function () {
            closeDrawer();
        });
    }

    // Tecla Esc: cierra el drawer (en móvil).
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            closeDrawer();
        }
    });

    // Al redimensionar a escritorio, asegurar que el drawer no quede abierto en móvil.
    window.addEventListener('resize', function () {
        if (isDesktop()) closeDrawer();
    });

    // ==========================================================================
    // BÚSQUEDA EN TIEMPO REAL + FILTROS (aplica a cualquier CRUD)
    // Las filas usan data-search (texto plano) y atributos data-* de filtro.
    // ==========================================================================
    const searchInput = document.getElementById('searchInput');
    const tableBody = document.getElementById('crudTableBody');

    function applyFilters(event) {
        if (!tableBody) return;

        const rows = tableBody.querySelectorAll('tr[data-search]');
        const categoryFilter = document.getElementById('filterCategory');
        const statusFilter = document.getElementById('filterStatus');
        const lowStockFilter = document.getElementById('filterLowStock');
        const genericFilters = document.querySelectorAll('.search-filter[data-filter]');

        const changedFilter = event && event.target;
        genericFilters.forEach(function (filter) {
            if (filter === changedFilter && filter.value === '') {
                genericFilters.forEach(function (other) {
                    if (other !== filter) other.value = '';
                });
            }
        });

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

            if (show) {
                genericFilters.forEach(function (filter) {
                    const attr = filter.getAttribute('data-filter');
                    const value = filter.value;
                    if (value && (row.getAttribute('data-' + attr) || '') !== value) show = false;
                });
            }

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
    // MÁSCARA DUI: solo dígitos, formato 00000000-0
    // ==========================================================================
    document.body.addEventListener('input', function (e) {
        const t = e.target;
        if (!(t instanceof HTMLInputElement) || !t.dataset.dui) return;
        let d = t.value.replace(/[^\d]/g, '');
        if (d.length > 9) d = d.slice(0, 9);
        if (d.length > 8) d = d.slice(0, 8) + '-' + d.slice(8);
        if (t.value !== d) t.value = d;
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
                const detailHtml = detailBtn.getAttribute('data-detail-html') || '';
                let summary = null;
                const summaryRaw = detailBtn.getAttribute('data-summary');
                if (summaryRaw) {
                    try { summary = JSON.parse(summaryRaw); } catch (err) { summary = null; }
                }

                let mediaHtml = '<div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-orange-100 to-orange-50">';
                if (image) {
                    mediaHtml += '<img src="' + image + '" alt="' + title + '"'
                        + ' class="absolute inset-0 w-full h-full object-cover"'
                        + ' onerror="this.remove()">';
                } else {
                    mediaHtml += '<i class="fa-solid ' + icon + ' text-8xl text-orange-400/50"></i>';
                }
                mediaHtml += '</div>';

                let summaryBlock = '';
                if (summary) {
                    summaryBlock = '<div class="rounded-2xl bg-white px-4 py-3.5 ring-1 ring-gray-100 shadow-sm space-y-3">';
                    for (const key in summary) {
                        const sv = (summary[key] !== null && summary[key] !== '' && summary[key] !== undefined) ? summary[key] : '—';
                        if (key === 'Nombre') {
                            summaryBlock += '<div><span class="block text-[11px] font-bold uppercase tracking-wider text-gray-400">Nombre</span>'
                                + '<span class="block text-base font-bold text-gray-900 leading-snug break-words">' + sv + '</span></div>';
                        } else if (key === 'Edad') {
                            summaryBlock += '<div class="flex items-center justify-between"><span class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Edad</span>'
                                + (sv !== '—' ? '<span class="inline-flex items-center rounded-full bg-green-50 ring-1 ring-green-200 text-green-700 px-2.5 py-0.5 text-xs font-bold">' + sv + '</span>' : '<span class="text-sm text-gray-400">' + sv + '</span>')
                                + '</div>';
                        } else {
                            summaryBlock += '<div><span class="block text-[11px] font-bold uppercase tracking-wider text-gray-400">' + key + '</span>'
                                + '<span class="block text-sm font-semibold text-gray-700 leading-snug break-words">' + sv + '</span></div>';
                        }
                    }
                    summaryBlock += '</div>';
                }

                const plain = modal.getAttribute('data-plain') !== null;

                let fields = '';
                for (const key in data) {
                    if (key === 'ID') continue;
                    if (summary && summary[key] !== undefined) continue;
                    const rawVal = data[key];
                    const val = (rawVal !== null && rawVal !== '' && rawVal !== undefined) ? rawVal : '—';
                    const isLong = key === 'Nombre' || key === 'Descripción' || key === 'Dirección' || String(val).length > 30;
                    const preserveLines = String(val).indexOf('\n') !== -1;
                    const valueStyle = preserveLines ? ' style="white-space:pre-wrap;word-break:break-word;"' : '';
                    fields += '<div class="' + (isLong ? 'sm:col-span-2 ' : '') + 'rounded-xl bg-white px-4 py-3 ring-1 ring-gray-100 min-w-0">'
                        + '<span class="block text-xs font-medium text-gray-500 mb-1">' + key + '</span>'
                        + '<span class="block text-sm font-semibold text-gray-900 leading-snug break-words"' + valueStyle + '>' + val + '</span>'
                        + '</div>';
                }

                let mediaColumn;
                if (plain) {
                    mediaColumn = '';
                } else if (summary) {
                    mediaColumn = '<div class="shrink-0 w-full md:w-64 flex flex-col gap-4 min-w-0">'
                        + '<div class="relative w-full h-52 rounded-2xl overflow-hidden ring-1 ring-orange-100 shadow-lg shadow-orange-100/60">' + mediaHtml + '</div>'
                        + summaryBlock
                        + '</div>';
                } else {
                    mediaColumn = '<div class="relative shrink-0 w-full md:w-64 h-52 md:h-full md:min-h-60 rounded-2xl overflow-hidden ring-1 ring-orange-100 shadow-lg shadow-orange-100/60">' + mediaHtml + '</div>';
                }

                body.innerHTML = '<div class="flex flex-col md:flex-row gap-6 items-start">'
                    + mediaColumn
                    + '<div class="flex-1 grid grid-cols-1 min-[480px]:grid-cols-2 gap-3 content-start min-w-0">' + fields + '</div>'
                    + '</div>'
                    + detailHtml;

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
    // CÁMARA PARA FOTOS (opción "Tomar foto" junto a cualquier input de archivo)
    // ==========================================================================
    const cameraModal = document.getElementById('cameraModal');
    const cameraTriggers = document.querySelectorAll('.camera-trigger');

    if (cameraModal && cameraTriggers.length) {
        const camVideo = document.getElementById('cameraVideo');
        const camCanvas = document.getElementById('cameraCanvas');
        const camError = document.getElementById('cameraError');
        const camCaptureBtn = document.getElementById('cameraCaptureBtn');
        const camSwitchBtn = document.getElementById('cameraSwitchBtn');
        let camStream = null;
        let camFacing = 'environment';
        let camTarget = null;

        function camStopStream() {
            if (camStream) {
                camStream.getTracks().forEach(function (track) { track.stop(); });
                camStream = null;
            }
            camVideo.srcObject = null;
        }

        function camStart() {
            camStopStream();
            camCaptureBtn.disabled = false;
            camSwitchBtn.disabled = false;
            camError.classList.add('hidden');
            camError.classList.remove('flex');

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                camError.classList.remove('hidden');
                camError.classList.add('flex');
                camCaptureBtn.disabled = true;
                camSwitchBtn.disabled = true;
                return;
            }

            navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: camFacing } }, audio: false })
                .then(function (stream) {
                    camStream = stream;
                    camVideo.srcObject = stream;
                    camVideo.play().catch(function () {});
                })
                .catch(function () {
                    camError.classList.remove('hidden');
                    camError.classList.add('flex');
                    camCaptureBtn.disabled = true;
                    camSwitchBtn.disabled = true;
                });
        }

        function camOpen(targetInput) {
            camTarget = targetInput;
            cameraModal.classList.add('open');
            document.body.style.overflow = 'hidden';
            camStart();
        }

        function camCloseModal() {
            cameraModal.classList.remove('open');
            document.body.style.overflow = '';
            camStopStream();
            camTarget = null;
        }

        cameraTriggers.forEach(function (btn) {
            btn.addEventListener('click', function () {
                const input = document.getElementById(btn.getAttribute('data-target'));
                if (input) camOpen(input);
            });
        });

        camCaptureBtn.addEventListener('click', function () {
            if (!camStream || !camTarget) return;
            const w = camVideo.videoWidth || 640;
            const h = camVideo.videoHeight || 480;
            camCanvas.width = w;
            camCanvas.height = h;
            camCanvas.getContext('2d').drawImage(camVideo, 0, 0, w, h);

            camCanvas.toBlob(function (blob) {
                if (!blob || !camTarget) return;
                const file = new File([blob], 'captura_' + Date.now() + '.jpg', { type: 'image/jpeg' });

                try {
                    const dt = new DataTransfer();
                    if (camTarget.hasAttribute('data-gallery') && camTarget.files) {
                        for (const f of camTarget.files) dt.items.add(f);
                    }
                    dt.items.add(file);
                    camTarget.files = dt.files;
                } catch (err) {}

                const preview = document.getElementById('camera-preview-' + camTarget.id);
                if (preview) {
                    preview.src = camCanvas.toDataURL('image/jpeg', 0.92);
                    preview.classList.remove('hidden');
                }
                camCloseModal();
            }, 'image/jpeg', 0.92);
        });

        camSwitchBtn.addEventListener('click', function () {
            camFacing = camFacing === 'environment' ? 'user' : 'environment';
            camStart();
        });

        cameraModal.querySelector('.camera-close').addEventListener('click', camCloseModal);
        cameraModal.addEventListener('click', function (e) {
            if (e.target === cameraModal) camCloseModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && cameraModal.classList.contains('open')) camCloseModal();
        });
    }

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
                confirmButtonColor: primaryColor(),
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