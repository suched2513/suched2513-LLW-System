    </main>

    <!-- Footer -->
    <footer class="p-8 border-t border-slate-100 text-center">
        <p class="text-xs text-slate-400 font-bold uppercase tracking-widest">
            © 2026 <?= htmlspecialchars(SCHOOL_NAME) ?> • Project Request System
        </p>
    </footer>
</div>

<script>
    // Global UI helpers
    function showToast(icon, title) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            icon: icon,
            title: title
        });
    }
</script>
</body>
</html>
