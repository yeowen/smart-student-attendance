// Loading Page Logic & Auto-redirect to Login
document.addEventListener("DOMContentLoaded", () => {
    const progressBar = document.getElementById("progress-bar");
    const percentageText = document.getElementById("percentage-text");
    const statusText = document.getElementById("status-text");
    const skipBtn = document.getElementById("skip-btn");
    const card = document.querySelector(".loading-card");

    const targetUrl = window.location.pathname.includes('/pub/') ? 'html/login.html' : 'login';
    let progress = 0;
    let isRedirecting = false;

    // Loading stages
    const stages = [
        { threshold: 20, message: "Initializing secure environment..." },
        { threshold: 45, message: "Loading QR recognition engine..." },
        { threshold: 75, message: "Synchronizing attendance modules..." },
        { threshold: 95, message: "Verifying faculty gateway..." },
        { threshold: 100, message: "Ready! Launching login portal..." }
    ];

    function updateProgress(value) {
        progress = Math.min(Math.round(value), 100);
        if (progressBar) progressBar.style.width = `${progress}%`;
        if (percentageText) percentageText.textContent = `${progress}%`;

        // Update status message based on current progress
        const currentStage = stages.find(s => progress <= s.threshold) || stages[stages.length - 1];
        if (statusText && statusText.textContent !== currentStage.message) {
            statusText.textContent = currentStage.message;
        }

        if (progress >= 100 && !isRedirecting) {
            redirectNow();
        }
    }

    function redirectNow() {
        if (isRedirecting) return;
        isRedirecting = true;
        if (card) {
            card.classList.add("fade-out");
        }
        setTimeout(() => {
            window.location.href = targetUrl;
        }, 400);
    }

    // Smooth progress simulation
    const startTime = performance.now();
    const duration = 2800; // Total duration in ms (~2.8s)

    function step(currentTime) {
        if (isRedirecting) return;
        const elapsed = currentTime - startTime;
        const rawProgress = (elapsed / duration) * 100;

        // Non-linear easing for natural realistic load feel
        const easeOutQuad = (t) => t * (2 - t);
        const normalized = Math.min(elapsed / duration, 1);
        const easedProgress = easeOutQuad(normalized) * 100;

        updateProgress(easedProgress);

        if (normalized < 1) {
            requestAnimationFrame(step);
        } else {
            updateProgress(100);
        }
    }

    requestAnimationFrame(step);

    // Skip Button Handler
    if (skipBtn) {
        skipBtn.addEventListener("click", () => {
            updateProgress(100);
            redirectNow();
        });
    }
});
