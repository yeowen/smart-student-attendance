// Faculty Login Interactive Logic
document.addEventListener("DOMContentLoaded", () => {
    const loginForm = document.getElementById("loginForm");
    const facultyIdInput = document.getElementById("facultyId");
    const passwordInput = document.getElementById("password");
    const togglePasswordBtn = document.getElementById("togglePasswordBtn");
    const eyeOpenIcon = document.getElementById("eyeOpenIcon");
    const eyeSlashIcon = document.getElementById("eyeSlashIcon");
    const submitBtn = document.getElementById("submitBtn");
    const btnSpinner = document.getElementById("btnSpinner");
    const btnLockIcon = document.getElementById("btnLockIcon");
    const btnText = document.getElementById("btnText");
    const contactAdminLink = document.getElementById("contactAdminLink");
    const toast = document.getElementById("toastNotification");
    const toastMessage = document.getElementById("toastMessage");
    const facultyIdError = document.getElementById("facultyIdError");
    const passwordError = document.getElementById("passwordError");
    const greetingTitle = document.getElementById("greetingTitle");

    let toastTimeout = null;

    // 0. Time-based dynamic greeting (Morning, Afternoon, Evening)
    function setDynamicGreeting() {
        if (!greetingTitle) return;
        const hour = new Date().getHours();
        let greeting = "Good morning";

        if (hour >= 12 && hour < 18) {
            greeting = "Good afternoon";
        } else if (hour >= 18 || hour < 5) {
            greeting = "Good evening";
        }

        greetingTitle.textContent = greeting;
    }
    setDynamicGreeting();

    // Dynamic Copyright Year
    const copyrightYearEl = document.getElementById("copyrightYear");
    if (copyrightYearEl) {
        copyrightYearEl.textContent = new Date().getFullYear();
    }

    // 1. Password Visibility Toggle
    if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener("click", () => {
            const isPassword = passwordInput.getAttribute("type") === "password";
            passwordInput.setAttribute("type", isPassword ? "text" : "password");
            
            if (isPassword) {
                eyeOpenIcon.classList.add("hidden");
                eyeSlashIcon.classList.remove("hidden");
                togglePasswordBtn.setAttribute("aria-label", "Hide password");
            } else {
                eyeOpenIcon.classList.remove("hidden");
                eyeSlashIcon.classList.add("hidden");
                togglePasswordBtn.setAttribute("aria-label", "Show password");
            }
        });
    }

    // 2. Clear error on typing
    facultyIdInput.addEventListener("input", () => {
        clearFieldError(facultyIdInput, facultyIdError);
    });

    passwordInput.addEventListener("input", () => {
        clearFieldError(passwordInput, passwordError);
    });

    function showFieldError(inputEl, errorEl, message) {
        const wrapper = inputEl.closest(".input-wrapper");
        if (wrapper) wrapper.classList.add("error");
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.add("visible");
        }
    }

    function clearFieldError(inputEl, errorEl) {
        const wrapper = inputEl.closest(".input-wrapper");
        if (wrapper) wrapper.classList.remove("error");
        if (errorEl) {
            errorEl.textContent = "";
            errorEl.classList.remove("visible");
        }
    }

    // 3. Form Validation & Submission
    loginForm.addEventListener("submit", (e) => {
        e.preventDefault();

        const facultyId = facultyIdInput.value.trim();
        const password = passwordInput.value;
        let hasError = false;

        // Validate Faculty ID or Email
        if (!facultyId) {
            showFieldError(facultyIdInput, facultyIdError, "Please enter your Faculty ID or email address");
            hasError = true;
        } else if (facultyId.length < 3) {
            showFieldError(facultyIdInput, facultyIdError, "Faculty ID must be at least 3 characters");
            hasError = true;
        } else {
            clearFieldError(facultyIdInput, facultyIdError);
        }

        // Validate Password
        if (!password) {
            showFieldError(passwordInput, passwordError, "Please enter your password");
            hasError = true;
        } else if (password.length < 4) {
            showFieldError(passwordInput, passwordError, "Password must be at least 4 characters");
            hasError = true;
        } else {
            clearFieldError(passwordInput, passwordError);
        }

        if (hasError) return;

        // Perform actual authentication via PHP Backend
        setLoadingState(true);

        const formData = new FormData();
        formData.append("facultyId", facultyId);
        formData.append("password", password);
        const rememberMe = document.getElementById("rememberMe")?.checked;
        if (rememberMe) {
            formData.append("rememberMe", "1");
        }

        const endpoint = "../../conf/php/login_process.php";

        fetch(endpoint, {
            method: "POST",
            body: formData
        })
        .then(async response => {
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseErr) {
                console.error("Server raw response:", text);
                throw new Error("Invalid server JSON response");
            }
            return data;
        })
        .then(data => {
            setLoadingState(false);
            if (data && data.success) {
                showToast(data.message || "Login successful! Redirecting...", "success");

                if (rememberMe) {
                    localStorage.setItem("smart_attendance_remember", "true");
                    localStorage.setItem("smart_attendance_user", facultyId);
                } else {
                    localStorage.removeItem("smart_attendance_remember");
                    localStorage.removeItem("smart_attendance_user");
                }

                setTimeout(() => {
                    window.location.href = data.redirect || "dashboard.html";
                }, 800);
            } else {
                const errorMsg = data && data.message ? data.message : "Invalid credentials";
                showToast(errorMsg, "error");
                showFieldError(passwordInput, passwordError, errorMsg);
            }
        })
        .catch(err => {
            setLoadingState(false);
            console.error("Login request failed:", err);
            showToast("Server connection error. Please try again.", "error");
        });
    });

    function setLoadingState(isLoading) {
        if (isLoading) {
            submitBtn.disabled = true;
            btnSpinner.classList.remove("hidden");
            btnLockIcon.classList.add("hidden");
            btnText.textContent = "Verifying access...";
        } else {
            submitBtn.disabled = false;
            btnSpinner.classList.add("hidden");
            btnLockIcon.classList.remove("hidden");
            btnText.textContent = "Sign in to dashboard";
        }
    }

    // 4. Toast Notification Helper
    function showToast(message, type = "info") {
        if (!toast || !toastMessage) return;

        if (toastTimeout) clearTimeout(toastTimeout);

        toast.className = `toast toast-${type}`;
        toastMessage.textContent = message;
        toast.classList.remove("hidden");

        toastTimeout = setTimeout(() => {
            toast.classList.add("hidden");
        }, 4000);
    }

    // 5. Help Link Handler
    if (contactAdminLink) {
        contactAdminLink.addEventListener("click", (e) => {
            e.preventDefault();
            showToast("Please contact IT Services at admin@school.edu", "info");
        });
    }

    // Pre-fill remembered username if available
    const savedUser = localStorage.getItem("smart_attendance_user");
    if (savedUser && facultyIdInput) {
        facultyIdInput.value = savedUser;
        const rememberCheckbox = document.getElementById("rememberMe");
        if (rememberCheckbox) rememberCheckbox.checked = true;
    }
});
