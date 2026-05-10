<!DOCTYPE html>
<html lang="th" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'LLW Management System' ?> | Lalom Wittaya</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    
    <!-- AdminLTE 4 (Bootstrap 5) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/css/adminlte.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AdminLTE JS -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@4.0.0-beta2/dist/js/adminlte.min.js"></script>

    <style>
        :root {
            --bs-body-font-family: 'Prompt', sans-serif !important;
        }
        body { 
            font-family: 'Prompt', sans-serif !important; 
        }
        .brand-link {
            text-decoration: none !important;
        }
        /* ─── Sidebar Active & Hover ─────────────────────────── */

        /* Active item — ทุกระดับ (parent + sub-menu) */
        .sidebar-menu .nav-link.active {
            background: linear-gradient(90deg, #0b5ed7 0%, #0d6efd 100%) !important;
            color: #fff !important;
            box-shadow: inset 4px 0 0 rgba(255,255,255,0.75),
                        0 2px 8px rgba(13,110,253,0.30) !important;
        }

        /* ไอคอน + ข้อความสีขาวทุกกรณี */
        .sidebar-menu .nav-link.active .nav-icon,
        .sidebar-menu .nav-link.active p,
        .sidebar-menu .nav-link.active .nav-arrow {
            color: #fff !important;
        }

        /* Hover สำหรับ item ที่ยังไม่ active */
        .sidebar-menu .nav-link:not(.active):hover {
            background: rgba(255,255,255,0.08) !important;
            color: #fff !important;
            box-shadow: inset 4px 0 0 rgba(255,255,255,0.25) !important;
            transition: all 0.15s ease !important;
        }

        /* Sub-menu active — สีเงิน/ขาว แทน gradient น้ำเงิน */
        .nav-treeview > .nav-item > .nav-link.active {
            background: rgba(255,255,255,0.14) !important;
            box-shadow: inset 4px 0 0 rgba(255,255,255,0.70) !important;
        }

        /* Sub-menu non-active — ข้อความสีเงิน */
        .nav-treeview > .nav-item > .nav-link:not(.active) {
            color: rgba(255,255,255,0.60) !important;
        }
        .nav-treeview > .nav-item > .nav-link:not(.active):hover {
            color: #fff !important;
        }

        /* Smooth transition ทุก nav-link */
        .sidebar-menu .nav-link {
            transition: background 0.15s ease, box-shadow 0.15s ease !important;
        }

        /* Sidebar Scroll Fix */
        .app-sidebar {
            height: 100vh !important;
            display: flex !important;
            flex-direction: column !important;
        }
        .sidebar-wrapper {
            flex: 1 1 auto !important;
            overflow-y: auto !important;
            overflow-x: hidden !important;
        }
        .sidebar-brand {
            flex: 0 0 auto !important;
        }
        .sidebar-footer {
            flex: 0 0 auto !important;
        }
        
        /* Indent Sub-menus */
        .nav-treeview > .nav-item > .nav-link {
            padding-left: 2.5rem !important;
            font-size: 0.85rem !important;
        }
        .nav-treeview > .nav-item > .nav-link i {
            font-size: 0.75rem !important;
            width: 1rem !important;
            text-align: center;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }

        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
        }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper">
    <script>
        // Persistence for AdminLTE 4 Sidebar
        document.addEventListener("DOMContentLoaded", function() {
            const body = document.body;
            const sidebarState = localStorage.getItem('sidebarState');
            if (sidebarState === 'collapsed') {
                body.classList.add('sidebar-collapse');
            }
            
            // Listen for toggle clicks to save state
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
