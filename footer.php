</div> <!-- container -->
    
    <!-- Footer -->
    <footer style="margin-top: 50px; padding: 20px 0; text-align: center; color: rgba(255,255,255,0.7); border-top: 1px solid rgba(255,255,255,0.1);">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Apartman Yönetim Sistemi. Tüm hakları saklıdır.</p>
            <p style="font-size: 12px; margin-top: 10px;">
                v2.0 - PHP Edition | 
                <a href="https://github.com" style="color: rgba(255,255,255,0.5);">GitHub</a> |
                <a href="mailto:support@apartman.com" style="color: rgba(255,255,255,0.5);">Destek</a>
            </p>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="assets/js/app.js"></script>
    
    <!-- Ekstra JavaScript kodları -->
    <script>
        // Sayfa yüklendiğinde çalışacak kodlar
        document.addEventListener('DOMContentLoaded', function() {
            // Otomatik form tarihleri
            const today = new Date().toISOString().split('T')[0];
            const currentMonth = new Date().toISOString().slice(0, 7);
            
            // Bugünün tarihi
            const dateInputs = document.querySelectorAll('input[type="date"]:not([value])');
            dateInputs.forEach(input => {
                if (!input.value) {
                    input.value = today;
                }
            });
            
            // Bu ayın değeri
            const monthInputs = document.querySelectorAll('input[type="month"]:not([value])');
            monthInputs.forEach(input => {
                if (!input.value) {
                    input.value = currentMonth;
                }
            });
            
            // Tooltip'ler
            const tooltipElements = document.querySelectorAll('[data-tooltip]');
            tooltipElements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = this.getAttribute('data-tooltip');
                    document.body.appendChild(tooltip);
                    
                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = rect.left + 'px';
                    tooltip.style.top = (rect.top - 35) + 'px';
                });
                
                element.addEventListener('mouseleave', function() {
                    const tooltip = document.querySelector('.tooltip');
                    if (tooltip) {
                        tooltip.remove();
                    }
                });
            });
            
       
            const confirmButtons = document.querySelectorAll('[data-confirm]');
            confirmButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const message = this.getAttribute('data-confirm');
                    if (!confirm(message)) {
                        e.preventDefault();
                    }
                });
            });
        });
        
   
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
        
  
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>