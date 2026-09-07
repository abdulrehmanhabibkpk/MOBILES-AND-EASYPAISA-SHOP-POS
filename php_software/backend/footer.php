    </main>
</div>

<script>
    // Initialize Lucide Icons
    lucide.createIcons();

    // Mobile Sidebar Toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const closeBtn = document.getElementById('closeSidebarBtn');
    const sidebar = document.getElementById('mainSidebar');

    if (mobileBtn && sidebar) {
        mobileBtn.addEventListener('click', () => {
            sidebar.classList.remove('translate-x-full');
        });
    }
    if (closeBtn && sidebar) {
        closeBtn.addEventListener('click', () => {
            sidebar.classList.add('translate-x-full');
        });
    }

    // Live Clock
    function updateClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('ur-PK', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const dateStr = now.toLocaleDateString('ur-PK', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        const el = document.getElementById('liveClock');
        if (el) el.innerText = dateStr + ' | ' + timeStr;
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>
</body>
</html>
