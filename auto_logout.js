/**
 * Auto Logout System for Alfina AC Mobil
 * - Auto logout after 60 minutes of inactivity
 * - Warning 5 minutes before logout
 * - Activity tracking (mouse, keyboard, scroll)
 */

class AutoLogout {
    constructor(config = {}) {
        // Default values (will be overridden by server config)
        this.timeoutDuration = config.timeout || 60 * 60 * 1000; // 60 minutes default
        this.warningTime = config.warning || 5 * 60 * 1000; // 5 minutes default
        this.checkInterval = 30 * 1000; // Check every 30 seconds
        
        this.lastActivity = Date.now();
        this.warningShown = false;
        this.logoutTimer = null;
        this.warningTimer = null;
        this.checkTimer = null;
        
        this.init();
    }
    
    init() {
        // Track user activity
        this.trackActivity();
        
        // Start checking for inactivity
        this.startChecking();
        
        // Update session on server periodically
        this.startSessionUpdater();
        
        console.log('Auto logout system initialized (2 minutes timeout for testing)');
    }
    
    trackActivity() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.resetActivity();
            }, true);
        });
    }
    
    resetActivity() {
        this.lastActivity = Date.now();
        this.warningShown = false;
        
        // Clear existing timers
        if (this.logoutTimer) {
            clearTimeout(this.logoutTimer);
        }
        if (this.warningTimer) {
            clearTimeout(this.warningTimer);
        }
        
        // Hide warning modal if shown
        this.hideWarning();
        
        // Set new timers
        this.setTimers();
    }
    
    setTimers() {
        // Warning timer (1.5 minutes for testing)
        this.warningTimer = setTimeout(() => {
            this.showWarning();
        }, this.timeoutDuration - this.warningTime);
        
        // Logout timer (2 minutes for testing)
        this.logoutTimer = setTimeout(() => {
            this.performLogout();
        }, this.timeoutDuration);
    }
    
    startChecking() {
        this.setTimers();
        
        // Periodic check
        this.checkTimer = setInterval(() => {
            const inactiveTime = Date.now() - this.lastActivity;
            
            if (inactiveTime >= this.timeoutDuration) {
                this.performLogout();
            } else if (inactiveTime >= (this.timeoutDuration - this.warningTime) && !this.warningShown) {
                this.showWarning();
            }
        }, this.checkInterval);
    }
    
    startSessionUpdater() {
        // Update session every 10 minutes
        setInterval(() => {
            fetch('update_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update_activity'
                })
            }).catch(console.error);
        }, 10 * 60 * 1000);
    }
    
    showWarning() {
        if (this.warningShown) return;
        
        this.warningShown = true;
        
        // Create warning modal if not exists
        if (!document.getElementById('logoutWarningModal')) {
            this.createWarningModal();
        }
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('logoutWarningModal'));
        modal.show();
        
        // Start countdown
        this.startCountdown();
    }
    
    createWarningModal() {
        const modalHtml = `
            <div class="modal fade" id="logoutWarningModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Peringatan Session
                            </h5>
                        </div>
                        <div class="modal-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                                <h5>Session Anda akan berakhir dalam:</h5>
                                <div class="display-4 text-danger" id="countdownTimer">0:30</div>
                            </div>
                            <p class="text-muted">
                                Klik "Tetap Login" untuk melanjutkan session, atau Anda akan otomatis logout.
                            </p>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <button type="button" class="btn btn-success" onclick="autoLogout.stayLoggedIn()">
                                <i class="fas fa-check me-2"></i>Tetap Login
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="autoLogout.performLogout()">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout Sekarang
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
    
    startCountdown() {
        let timeLeft = this.warningTime / 1000; // 30 seconds for testing
        const countdownElement = document.getElementById('countdownTimer');
        
        const countdown = setInterval(() => {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            
            countdownElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
                this.performLogout();
                return;
            }
            
            timeLeft--;
        }, 1000);
        
        // Store countdown for cleanup
        this.countdownInterval = countdown;
    }
    
    stayLoggedIn() {
        // Reset activity
        this.resetActivity();
        
        // Hide warning
        this.hideWarning();
        
        // Update session on server
        fetch('update_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'extend_session'
            })
        }).catch(console.error);
        
        console.log('Session extended by user action');
    }
    
    hideWarning() {
        const modal = document.getElementById('logoutWarningModal');
        if (modal) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        }
        
        // Clear countdown
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
    }
    
    performLogout() {
        // Clear all timers
        if (this.logoutTimer) clearTimeout(this.logoutTimer);
        if (this.warningTimer) clearTimeout(this.warningTimer);
        if (this.checkTimer) clearInterval(this.checkTimer);
        if (this.countdownInterval) clearInterval(this.countdownInterval);
        
        // Show logout message
        this.showLogoutMessage();
        
        // Perform logout after 3 seconds
        setTimeout(() => {
            window.location.href = 'login.php?logout=timeout';
        }, 3000);
    }
    
    showLogoutMessage() {
        // Create logout message modal
        const logoutHtml = `
            <div class="modal fade show" id="logoutModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.8);">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Session Berakhir
                            </h5>
                        </div>
                        <div class="modal-body text-center">
                            <div class="mb-3">
                                <i class="fas fa-clock fa-3x text-danger mb-3"></i>
                                <h5>Session Anda telah berakhir</h5>
                                <p class="text-muted">
                                    Anda akan diarahkan ke halaman login dalam 3 detik...
                                </p>
                            </div>
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', logoutHtml);
    }
    
    // Public method to manually extend session
    extendSession() {
        this.resetActivity();
    }
    
    // Public method to get remaining time
    getRemainingTime() {
        const elapsed = Date.now() - this.lastActivity;
        const remaining = this.timeoutDuration - elapsed;
        return Math.max(0, remaining);
    }
    
    // Public method to get remaining time formatted
    getRemainingTimeFormatted() {
        const remaining = this.getRemainingTime();
        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
}

// Initialize auto logout when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if user is logged in (check if we have session data)
    if (document.body.dataset.userRole) {
        window.autoLogout = new AutoLogout();
        
        // Add session timer to navbar if exists
        const navbar = document.querySelector('.navbar, .sidebar');
        if (navbar) {
            const timerHtml = `
                <div class="session-timer text-white-50 small" style="position: fixed; bottom: 10px; right: 10px; z-index: 1000;">
                    <i class="fas fa-clock me-1"></i>
                    <span id="sessionTimer">2:00</span>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', timerHtml);
            
            // Update timer display every 5 seconds for testing
            setInterval(() => {
                const timerElement = document.getElementById('sessionTimer');
                if (timerElement && window.autoLogout) {
                    timerElement.textContent = window.autoLogout.getRemainingTimeFormatted();
                }
            }, 5000); // Update every 5 seconds for testing
        }
    }
});