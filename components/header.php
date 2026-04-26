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
        .sidebar-menu .nav-link.active {
            background-color: #0d6efd !important;
            color: #fff !important;
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
