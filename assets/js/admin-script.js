        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('educoreSidebar');
            const toggleBtn = document.getElementById('educoreToggleSidebar');
            
            if (sidebar && toggleBtn) {
                if (localStorage.getItem('educore_sidebar_collapsed') === 'true') {
                    sidebar.classList.add('collapsed');
                }

                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    localStorage.setItem('educore_sidebar_collapsed', sidebar.classList.contains('collapsed'));
                });
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            function updateClock() {
                var now = new Date();
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var seconds = String(now.getSeconds()).padStart(2, '0');
                var clockElem = document.getElementById('educoreLiveDashboardClock');
                if (clockElem) {
                    clockElem.textContent = hours + ':' + minutes + ':' + seconds;
                }
            }
            updateClock();
            setInterval(updateClock, 1000);
        });

        