    </div>
    
    <footer class="text-center py-5 text-gray-400 text-sm mt-10 border-t border-gray-200 bg-white">
        <p>&copy; <?= date('Y') ?> Monitoring Pelatihan Pegawai - BPOM</p>
    </footer>
    
    <script>
        // Close modal on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.classList.remove('show');
            });
        });
        
        // Auto hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('[class*="alert"], .animate-fade-in').forEach(el => {
                if (el.classList.contains('bg-green-100') || el.classList.contains('bg-red-100') || el.classList.contains('bg-yellow-100') || el.classList.contains('bg-blue-100')) {
                    el.style.transition = 'opacity 0.3s ease';
                    el.style.opacity = '0';
                    setTimeout(() => el.remove(), 300);
                }
            });
        }, 5000);
        
        // Add loading state to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const btn = this.querySelector('button[type="submit"]');
                if (btn && !btn.classList.contains('bg-red-500')) {
                    btn.disabled = true;
                    btn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...';
                }
            });
        });
    </script>
</body>
</html>
