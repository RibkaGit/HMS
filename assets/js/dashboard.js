// ============================================================================
// DASHBOARD JAVASCRIPT
// ============================================================================

document.addEventListener('DOMContentLoaded', function() {
    // Mobile Menu Toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('open');
        });
    }

    // Close sidebar on outside click (mobile)
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        }
    });

    // Chart.js - Patient Visits Overview
    const ctx = document.getElementById('visitsChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'OPD',
                    data: [12, 19, 15, 17, 14, 8, 6],
                    backgroundColor: 'rgba(37, 99, 235, 0.7)',
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 2,
                    borderRadius: 4
                }, {
                    label: 'IPD',
                    data: [5, 7, 6, 8, 9, 4, 3],
                    backgroundColor: 'rgba(139, 92, 246, 0.7)',
                    borderColor: 'rgba(139, 92, 246, 1)',
                    borderWidth: 2,
                    borderRadius: 4
                }, {
                    label: 'Emergency',
                    data: [3, 5, 4, 6, 4, 7, 5],
                    backgroundColor: 'rgba(239, 68, 68, 0.7)',
                    borderColor: 'rgba(239, 68, 68, 1)',
                    borderWidth: 2,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12,
                                family: 'Inter'
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }

    // Chart Period Switch
    const chartPeriod = document.getElementById('chartPeriod');
    if (chartPeriod) {
        chartPeriod.addEventListener('change', function() {
            // In a real implementation, this would fetch new data via AJAX
            console.log('Period changed to:', this.value);
            // You would update the chart data here
        });
    }

    // Search functionality
    const searchInput = document.querySelector('.search-box input');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = this.value.trim();
                if (query.length > 2) {
                    // In a real implementation, this would trigger a search
                    console.log('Searching for:', query);
                }
            }, 500);
        });
    }

    // Notification click
    const notifications = document.querySelector('.notifications');
    if (notifications) {
        notifications.addEventListener('click', function() {
            const badge = this.querySelector('.badge');
            if (badge) {
                badge.textContent = '0';
                badge.style.display = 'none';
            }
            // In a real implementation, this would open notification panel
            console.log('Notifications clicked');
        });
    }

    // Auto-refresh for real-time data (optional)
    // setInterval(() => {
    //     // Refresh stats or notifications
    // }, 30000);
});

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '12-hour',
        minute: '2-digit'
    }).format(date);
}

// Time ago
function timeAgo(dateString) {
    const now = new Date();
    const past = new Date(dateString);
    const diffMs = now - past;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return diffMins + 'm ago';
    if (diffHours < 24) return diffHours + 'h ago';
    if (diffDays < 7) return diffDays + 'd ago';
    return formatDate(dateString);
}

// Toast notification
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 
                         type === 'error' ? 'exclamation-circle' : 
                         type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
        <span>${message}</span>
        <button class="toast-close"><i class="fas fa-times"></i></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, 5000);
}