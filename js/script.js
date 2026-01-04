// Simple gamelike animations
document.addEventListener('DOMContentLoaded', function() {
    createBackgroundElements();
    addInteractiveAnimations();
    startStatsAnimation();
    startAutoUpdateStats(); // Start auto-updating stats
});

function createBackgroundElements() {
    const bgContainer = document.getElementById('bgElements');
    const elementCount = 8;
    
    for (let i = 0; i < elementCount; i++) {
        const element = document.createElement('div');
        element.className = 'bg-element';
        
        // Random properties
        const size = Math.random() * 60 + 20;
        const posX = Math.random() * 100;
        const posY = Math.random() * 100;
        const delay = Math.random() * 6;
        const duration = Math.random() * 4 + 4;
        
        element.style.width = `${size}px`;
        element.style.height = `${size}px`;
        element.style.left = `${posX}%`;
        element.style.top = `${posY}%`;
        element.style.animationDelay = `${delay}s`;
        element.style.animationDuration = `${duration}s`;
        
        bgContainer.appendChild(element);
    }
}

function addInteractiveAnimations() {
    // Add click effects to buttons
    document.querySelectorAll('.game-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            createRippleEffect(e, this);
        });
    });
    
    // Add focus animations to inputs
    document.querySelectorAll('.game-input').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });
}

function startStatsAnimation() {
    // Animate the initial numbers with counting effect
    const stats = document.querySelectorAll('.stat-value');
    stats.forEach(stat => {
        const finalValue = parseInt(stat.textContent);
        if (finalValue > 0) {
            animateCount(stat, 0, finalValue, 2000);
        }
    });
}

function animateCount(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const value = Math.floor(progress * (end - start) + start);
        element.textContent = value.toLocaleString();
        
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

function startAutoUpdateStats() {
    // Update stats every 30 seconds to reflect real-time data
    setInterval(updateLiveStats, 30000);
}

function updateLiveStats() {
    // Fetch updated stats from the server
    fetch('get_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update points counter with animation
                updateCounter('pointsCounter', data.total_points);
                
                // Update streaks counter with animation
                updateCounter('streaksCounter', data.total_streaks);
                
                // Update badges counter with animation
                updateCounter('badgesCounter', data.total_badges);
                
                // Update users counter with animation
                updateCounter('usersCounter', data.total_users);
            }
        })
        .catch(error => {
            console.log('Error updating stats:', error);
        });
}

function updateCounter(elementId, newValue) {
    const element = document.getElementById(elementId);
    const currentValue = parseInt(element.textContent.replace(/,/g, ''));
    
    if (currentValue !== newValue) {
        animateCount(element, currentValue, newValue, 1000);
    }
}

function createRippleEffect(event, element) {
    const ripple = document.createElement('span');
    const rect = element.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.cssText = `
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        transform: scale(0);
        animation: ripple 0.6s linear;
        width: ${size}px;
        height: ${size}px;
        left: ${x}px;
        top: ${y}px;
        pointer-events: none;
    `;
    
    element.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

// Add ripple animation to CSS
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    .game-btn {
        overflow: hidden;
        position: relative;
    }
`;
<script>
// Add this script to your main layout file or include it separately

document.addEventListener('DOMContentLoaded', function() {
    // Fix for mobile viewport height issues
    function setViewportHeight() {
        let vh = window.innerHeight * 0.01;
        document.documentElement.style.setProperty('--vh', `${vh}px`);
    }
    
    setViewportHeight();
    window.addEventListener('resize', setViewportHeight);
    window.addEventListener('orientationchange', setViewportHeight);
    
    // Fix for iOS form zoom
    document.addEventListener('touchstart', function() {}, {passive: true});
    
    // Prevent double-tap zoom on mobile
    let lastTouchEnd = 0;
    document.addEventListener('touchend', function(event) {
        const now = (new Date()).getTime();
        if (now - lastTouchEnd <= 300) {
            event.preventDefault();
        }
        lastTouchEnd = now;
    }, false);
    
    // Handle orientation changes
    window.addEventListener('orientationchange', function() {
        setTimeout(() => {
            window.scrollTo(0, 0);
        }, 100);
    });
    
    // Fix for mobile keyboard issues
    const inputs = document.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            setTimeout(() => {
                document.body.scrollTop = 0;
            }, 100);
        });
    });
});
</script>
document.head.appendChild(rippleStyle);