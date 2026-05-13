<!DOCTYPE html>
<html lang="th" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'LLW Management System' ?> | Lalom Wittaya</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">

    <!-- CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>

    <style>
        :root {
            --bs-body-font-family: 'Prompt', sans-serif !important;
            --sidebar-dark:   #0b1426;
            --sidebar-mid:    #111c35;
            --sidebar-light:  #1a2847;
            --sidebar-active: #2563eb;
        }
        body { font-family: 'Prompt', sans-serif !important; }

        /* ══════════════════════════════════════════════════
           SIDEBAR — Deep Navy Theme
        ══════════════════════════════════════════════════ */
        .app-sidebar {
            background: linear-gradient(175deg, var(--sidebar-mid) 0%, var(--sidebar-dark) 100%) !important;
            height: 100vh !important;
            display: flex !important;
            flex-direction: column !important;
        }

        /* Brand bar */
        .sidebar-brand {
            flex: 0 0 auto !important;
            background: rgba(0,0,0,0.35) !important;
            border-bottom: 1px solid rgba(255,255,255,0.07) !important;
        }
        .brand-link { text-decoration: none !important; }
        .brand-text {
            color: #f1f5f9 !important;
            font-size: 1rem !important;
            font-weight: 600 !important;
            letter-spacing: 0.04em;
        }

        /* Scroll wrapper */
        .sidebar-wrapper {
            flex: 1 1 auto !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }
        .sidebar-footer { flex: 0 0 auto !important; }

        /* Section labels */
        .nav-header {
            color: rgba(148,163,184,0.45) !important;
            font-size: 0.59rem !important;
            font-weight: 800 !important;
            letter-spacing: 0.16em !important;
            padding: 1.3rem 1rem 0.3rem !important;
        }

        /* All nav links — base */
        .sidebar-menu .nav-link {
            color: rgba(203,213,225,0.78) !important;
            border-radius: 10px !important;
            margin: 1px 8px !important;
            transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease !important;
        }
        .sidebar-menu .nav-link .nav-icon,
        .sidebar-menu .nav-link p {
            color: rgba(148,163,184,0.65) !important;
            transition: color 0.15s ease !important;
        }

        /* Active nav link — blue pill with glow */
        .sidebar-menu .nav-link.active {
            background: linear-gradient(90deg, #1d4ed8 0%, #3b82f6 100%) !important;
            color: #fff !important;
            box-shadow: 0 4px 16px rgba(37,99,235,0.40) !important;
        }
        .sidebar-menu .nav-link.active .nav-icon,
        .sidebar-menu .nav-link.active p,
        .sidebar-menu .nav-link.active .nav-arrow {
            color: #fff !important;
        }

        /* Hover (non-active) */
        .sidebar-menu .nav-link:not(.active):hover {
            background: rgba(255,255,255,0.07) !important;
            color: #f1f5f9 !important;
        }
        .sidebar-menu .nav-link:not(.active):hover .nav-icon,
        .sidebar-menu .nav-link:not(.active):hover p {
            color: #93c5fd !important;
        }

        /* Sub-menu links */
        .nav-treeview > .nav-item > .nav-link {
            padding-left: 2.5rem !important;
            font-size: 0.83rem !important;
            border-radius: 8px !important;
            margin: 1px 8px 1px 16px !important;
            color: rgba(148,163,184,0.72) !important;
        }
        .nav-treeview > .nav-item > .nav-link .nav-icon,
        .nav-treeview > .nav-item > .nav-link i {
            font-size: 0.72rem !important;
            width: 1rem !important;
            text-align: center;
        }

        /* Sub-menu active — light blue tint + left bar */
        .nav-treeview > .nav-item > .nav-link.active {
            background: rgba(59,130,246,0.15) !important;
            color: #93c5fd !important;
            box-shadow: inset 3px 0 0 #3b82f6 !important;
        }
        .nav-treeview > .nav-item > .nav-link.active .nav-icon,
        .nav-treeview > .nav-item > .nav-link.active i {
            color: #93c5fd !important;
        }

        /* Sub-menu non-active */
        .nav-treeview > .nav-item > .nav-link:not(.active) {
            color: rgba(100,116,139,0.9) !important;
        }
        .nav-treeview > .nav-item > .nav-link:not(.active):hover {
            background: rgba(255,255,255,0.05) !important;
            color: #e2e8f0 !important;
        }
        .nav-treeview > .nav-item > .nav-link:not(.active):hover .nav-icon,
        .nav-treeview > .nav-item > .nav-link:not(.active):hover i {
            color: #93c5fd !important;
        }

        /* Sidebar footer */
        .sidebar-footer {
            background: rgba(0,0,0,0.32) !important;
            border-top: 1px solid rgba(255,255,255,0.07) !important;
        }

        /* ══════════════════════════════════════════════════
           TOP NAVBAR
        ══════════════════════════════════════════════════ */
        .app-header {
            background: #fff !important;
            border-bottom: 1px solid #e2e8f0 !important;
            box-shadow: 0 1px 10px rgba(0,0,0,0.06) !important;
        }

        /* Scrollbar */
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }

        /* ══════════════════════════════════════════════════
           MODAL - Global High-Layer Fix
        ══════════════════════════════════════════════════ */
        .modal { z-index: 20000 !important; }
        .modal-backdrop { z-index: 19999 !important; }
        .modal-content { 
            border: none !important; 
            border-radius: 1.5rem !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
        }
        .modal-open { overflow: hidden !important; padding-right: 0 !important; }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
        }
    </style>
    
    <script>
        /**
         * Global Modal Handler — Ensure all modals are moved to body root 
         * to bypass stacking context issues in complex layouts.
         */
        (function() {
            function relocateModals() {
                document.querySelectorAll('.modal').forEach(modal => {
                    if (modal.parentElement !== document.body && !modal.closest('.modal-content')) {
                        document.body.appendChild(modal);
                    }
                });
            }

            // Run on load and watch for new modals
            document.addEventListener('DOMContentLoaded', relocateModals);
            const observer = new MutationObserver((mutations) => {
                mutations.forEach(m => {
                    if (m.addedNodes.length) relocateModals();
                });
            });
            observer.observe(document.documentElement, { childList: true, subtree: true });

            // Fix for multiple backdrops / stuck scroll
            document.addEventListener('hidden.bs.modal', function () {
                if (document.querySelectorAll('.modal.show').length === 0) {
                    document.body.classList.remove('modal-open');
                    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
                }
            });
        })();
    </script>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const body = document.body;
            if (localStorage.getItem('sidebarState') === 'collapsed') {
                body.classList.add('sidebar-collapse');
            }
            document.querySelectorAll('[data-lte-toggle="sidebar"]').forEach(btn => {
                btn.addEventListener('click', () => {
                    setTimeout(() => {
                        const state = body.classList.contains('sidebar-collapse') ? 'collapsed' : 'expanded';
                        localStorage.setItem('sidebarState', state);
                    }, 300);
                });
            });
        });
    </script>
