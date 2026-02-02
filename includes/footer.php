<?php
// includes/footer.php
?>
        </div> <!-- Cierre del container-fluid -->
    </main>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- Scripts personalizados -->
    <script>
        // Inicializar DataTables en todas las tablas con clase 'datatable'
        $(document).ready(function() {
            $('.datatable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                pageLength: 10,
                responsive: true
            });
            
            // Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Auto-ocultar alertas después de 5 segundos
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
        
        // Formato de moneda
        function formatCurrency(value) {
            return '$' + parseFloat(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }
        
        // Formato de números
        function formatNumber(value) {
            return parseInt(value).toLocaleString('es-VE');
        }
        
        // Confirmación antes de eliminar
        function confirmDelete(message = '¿Está seguro de eliminar este registro?') {
            return confirm(message);
        }
    </script>
    
    <footer class="mt-5 py-3 border-top text-center text-muted">
        <div class="container">
            <small>
                <i class="bi bi-c-circle"></i> 2026 <?php echo SITE_NAME; ?> - Sistema de Gestión Integral 
                | <span class="text-primary">v2.0</span> 
                | <i class="bi bi-clock"></i> <?php echo date('d/m/Y H:i:s'); ?>
            </small>
        </div>
    </footer>
</body>
</html>