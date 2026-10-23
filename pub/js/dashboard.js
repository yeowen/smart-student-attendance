// Faculty Dashboard Client Logic - Pure JS communicating with PHP Backend
document.addEventListener("DOMContentLoaded", () => {
    // 1. Fetch Dashboard Data from PHP Backend API
    fetch("../../conf/php/dashboard_data.php")
        .then(res => {
            if (res.status === 401) {
                window.location.href = "login.html";
                return null;
            }
            return res.json();
        })
        .then(data => {
            if (!data || !data.success) return;
            renderDashboard(data);
        })
        .catch(err => {
            console.error("Failed to load dashboard data:", err);
        });

    function renderDashboard(data) {
        const u = data.user;
        const g = data.greeting;
        const m = data.metrics;
        const w = data.weekly_chart;
        const classes = data.today_classes;

        // Topbar & Sidebar User Info
        setText("sidebarAvatar", u.avatar_initials || "CP");
        setText("topbarAvatar", u.avatar_initials || "CP");
        setText("sidebarUserName", `Prof. ${u.name}`);
        setText("sidebarFacultyId", `Faculty ID ${u.faculty_id}`);
        setText("topbarName", u.name);
        setText("topbarRole", u.title || "Professor");
        setText("topbarDate", g.date_formatted);

        // Hero Greeting
        setText("heroDateTag", g.header_date);
        setText("heroGreeting", `${g.time_greeting}, ${u.title || 'Professor'} ${g.last_name}`);

        // 4 Metric Cards
        setText("metricActiveSections", m.active_sections);
        setText("metricTodayScheduled", `${m.today_scheduled_classes} classes scheduled today`);
        setText("metricTotalStudents", m.total_students);
        setText("metricSectionsSummary", `Across ${m.active_sections} registered sections`);
        setText("metricTodayAttendance", `${m.today_attendance_percent}%`);
        setText("metricPresentCount", `${m.present_today} of ${m.total_expected_today} students recorded`);
        setText("metricActiveSessions", m.active_sessions_count);
        setText("metricActiveSessionDesc", m.active_session_name ? `${m.active_session_name} · Live session ongoing` : "No active sessions right now");

        // Weekly Barchart
        setText("weeklyAverageNumber", `${w.average_percent}%`);
        const barsContainer = document.getElementById("weeklyBarsContainer");
        if (barsContainer && Array.isArray(w.bars)) {
            barsContainer.innerHTML = w.bars.map(bar => `
                <div class="bar-column">
                    <div class="bar-pill" style="height: ${bar.percent}%;" title="${bar.day}: ${bar.percent}%">
                        <div class="bar-tooltip">${bar.percent}%</div>
                    </div>
                    <span class="bar-label">${bar.day}</span>
                </div>
            `).join("");
        }

        // Today's Classes Table
        const tbody = document.getElementById("todayClassesTableBody");
        if (tbody) {
            if (classes && classes.length > 0) {
                tbody.innerHTML = classes.map(c => {
                    const status = c.session_status || "scheduled";
                    let statusBadge = `<span class="status-pill status-upcoming">Upcoming</span>`;
                    if (status === "active") {
                        statusBadge = `<span class="status-pill status-active"><span class="live-dot"></span>Active Now</span>`;
                    } else if (status === "completed") {
                        statusBadge = `<span class="status-pill status-completed">Completed</span>`;
                    }

                    const timeStr = `${formatTime(c.start_time)} - ${formatTime(c.end_time)}`;

                    return `
                        <tr>
                            <td>
                                <div class="section-cell">
                                    <span class="section-badge-icon">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0d8ae5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <rect width="18" height="18" x="3" y="3" rx="2"/>
                                            <path d="M9 3v18"/>
                                        </svg>
                                    </span>
                                    <div>
                                        <strong class="cell-section-name">${escapeHtml(c.section_name)}</strong>
                                        <span class="cell-subject-desc">${escapeHtml(c.subject_code)} &middot; ${escapeHtml(c.subject_name)}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="schedule-cell">
                                    <span class="schedule-time">${timeStr}</span>
                                    <span class="schedule-room">${escapeHtml(c.room)}</span>
                                </div>
                            </td>
                            <td>
                                <span class="students-badge">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                    </svg>
                                    ${c.student_count} students
                                </span>
                            </td>
                            <td>${statusBadge}</td>
                            <td>
                                <a href="section_details.html?id=${c.id}" class="table-action-btn">View</a>
                            </td>
                        </tr>
                    `;
                }).join("");
            } else {
                tbody.innerHTML = `<tr><td colspan="5" class="empty-state-cell">No scheduled classes found.</td></tr>`;
            }
        }
    }

    // 2. Search Filter for Classes Table
    const searchInput = document.getElementById("globalSearchInput");
    if (searchInput) {
        searchInput.addEventListener("input", (e) => {
            const query = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll("#todayClassesTableBody tr");
            rows.forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(query) ? "" : "none";
            });
        });

        document.addEventListener("keydown", (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === "k") {
                e.preventDefault();
                searchInput.focus();
            }
        });

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
    }

    function setText(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val;
    }

    function formatTime(t) {
        if (!t) return "";
        const parts = t.split(":");
        let h = parseInt(parts[0], 10);
        const m = parts[1];
        const ampm = h >= 12 ? "PM" : "AM";
        h = h % 12 || 12;
        return `${h}:${m} ${ampm}`;
    }

    function escapeHtml(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }
});
