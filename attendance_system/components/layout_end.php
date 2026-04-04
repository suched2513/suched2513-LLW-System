
    <!-- CONTENT ENDS HERE -->
</main>

<script>
    // Sidebar toggle logic for mobile (if needed later)
    const toggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('absolute');
        });
    }

    // Auto-dismiss SweetAlert (if using default)
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.focus();
    }
</script>

</body>
</html>
