// Faculty Settings Client Logic
document.addEventListener("DOMContentLoaded", () => {
    loadSettings();

    function loadSettings() {
        fetch("../../conf/php/settings_data.php")
            .then(res => {
                if (res.status === 401) {
                    window.location.href = "login.html";
                    return null;
                }
                return res.json();
            })
            .then(data => {
                if (!data || !data.success) return;
                const u = data.user;
                document.getElementById("topbarDate").textContent = data.today_date;
                document.getElementById("sidebarAvatar").textContent = u.avatar_initials || "CP";
                document.getElementById("topbarAvatar").textContent = u.avatar_initials || "CP";
                document.getElementById("sidebarUserName").textContent = `Prof. ${u.name}`;
                document.getElementById("sidebarFacultyId").textContent = `Faculty ID ${u.faculty_id}`;
                document.getElementById("topbarName").textContent = u.name;
                document.getElementById("topbarRole").textContent = u.title || "Professor";

                document.getElementById("facultyIdInput").value = u.faculty_id;
                document.getElementById("nameInput").value = u.name;
                document.getElementById("emailInput").value = u.email;
                document.getElementById("titleInput").value = u.title || "Professor";

                const reminderToggle = document.getElementById("scheduleReminderToggle");
                const isReminderOn = parseInt(u.schedule_reminder, 10) === 1;
                if (reminderToggle) reminderToggle.checked = isReminderOn;
                updateReminderBadge(isReminderOn);
            })
            .catch(err => {
                console.error("Error loading settings:", err);
            });
    }

    // Profile Form Submit
    const profileForm = document.getElementById("profileForm");
    if (profileForm) {
        profileForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const formData = new FormData(profileForm);

            fetch("../../conf/php/settings_data.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showAlert(res.message, "success");
                    loadSettings();
                } else {
                    showAlert(res.message, "error");
                }
            })
            .catch(err => {
                showAlert("Failed to update profile", "error");
            });
        });
    }

    // Password Form Submit
    const passwordForm = document.getElementById("passwordForm");
    if (passwordForm) {
        passwordForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const formData = new FormData(passwordForm);

            fetch("../../conf/php/settings_data.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showAlert(res.message, "success");
                    passwordForm.reset();
                } else {
                    showAlert(res.message, "error");
                }
            })
            .catch(err => {
                showAlert("Failed to update password", "error");
            });
        });
    }

    document.querySelectorAll(".password-visibility-toggle").forEach((button) => {
        button.addEventListener("click", () => {
            const input = document.getElementById(button.dataset.passwordTarget);
            if (!input) return;
            const showing = input.type === "password";
            input.type = showing ? "text" : "password";
            button.setAttribute("aria-pressed", String(showing));
            button.setAttribute("aria-label", `${showing ? "Hide" : "Show"} ${input.id === "oldPasswordInput" ? "current" : "new"} password`);
            button.title = showing ? "Hide password" : "Show password";
        });
    });

    // Class Schedule Reminder Toggle
    const reminderToggle = document.getElementById("scheduleReminderToggle");
    const reminderBadge = document.getElementById("reminderBadgeStatus");

    function updateReminderBadge(isOn) {
        if (!reminderBadge) return;
        if (isOn) {
            reminderBadge.textContent = "Active (Reminders ON)";
            reminderBadge.style.background = "#dcfce7";
            reminderBadge.style.color = "#15803d";
        } else {
            reminderBadge.textContent = "Disabled (Reminders OFF)";
            reminderBadge.style.background = "#f1f5f9";
            reminderBadge.style.color = "#64748b";
        }
    }

    if (reminderToggle) {
        reminderToggle.addEventListener("change", () => {
            const isChecked = reminderToggle.checked;
            updateReminderBadge(isChecked);

            const formData = new FormData();
            formData.append("action", "toggle_schedule_reminder");
            formData.append("schedule_reminder", isChecked ? "1" : "0");

            fetch("../../conf/php/settings_data.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showAlert(res.message, "success");
                } else {
                    showAlert(res.message, "error");
                    reminderToggle.checked = !isChecked;
                    updateReminderBadge(!isChecked);
                }
            })
            .catch(err => {
                console.error("Error toggling reminder:", err);
                showAlert("Failed to update reminder preference", "error");
                reminderToggle.checked = !isChecked;
                updateReminderBadge(!isChecked);
            });
        });
    }

    function showAlert(msg, type) {
        const box = document.getElementById("alertFeedback");
        if (!box) return;
        box.textContent = msg;
        box.style.background = type === "success" ? "#dcfce7" : "#fee2e2";
        box.style.color = type === "success" ? "#15803d" : "#b91c1c";
        box.classList.remove("hidden");
        setTimeout(() => box.classList.add("hidden"), 4000);
    }

    document.addEventListener("click", (e) => {
        const btn = e.target.closest(".search-box-btn");
        if (btn) {
            const box = btn.closest(".search-box");
            if (box) {
                const input = box.querySelector("input");
                if (input) {
                    input.focus();
                    input.dispatchEvent(new Event("input", { bubbles: true }));
                }
            }
        }
    });
});
