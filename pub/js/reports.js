// Attendance Reports Client Logic
document.addEventListener("DOMContentLoaded", () => {
    let state = {
        sections: [],
        selectedSectionId: 0,
        currentSection: null,
        sessions: [],
        selectedSessionId: 0,
        selectedSession: null,
        students: [],
        filterStatus: "all",
        searchQuery: "",
        sectionClicked: false  // panel only shows after explicit card click
    };

    // Initial Load
    loadReportsData();

    // Close button — hides the detail panel
    document.addEventListener("click", (e) => {
        if (e.target.closest("#closeDetailPanelBtn")) {
            const panel = document.getElementById("reportDetailPanel");
            if (panel) {
                panel.style.animation = "fadeOutDown 0.25s ease forwards";
                setTimeout(() => {
                    panel.style.display = "none";
                    panel.style.animation = "";
                }, 230);
            }
            state.sectionClicked = false;
            // Remove active highlight from all cards
            document.querySelectorAll(".section-card-item").forEach(c => c.classList.remove("active"));
            state.selectedSectionId = 0;
        }
    });

    function loadReportsData(secId = 0, sessId = 0) {
        let url = "../../conf/php/reports_data.php";
        const params = [];
        if (secId > 0) params.push(`section_id=${encodeURIComponent(secId)}`);
        if (sessId > 0) params.push(`session_id=${encodeURIComponent(sessId)}`);
        if (params.length > 0) url += "?" + params.join("&");

        fetch(url)
            .then(res => {
                if (res.status === 401) {
                    window.location.href = "login.html";
                    return null;
                }
                return res.json();
            })
            .then(data => {
                if (!data || !data.success) return;

                state.sections = data.sections || [];
                state.selectedSectionId = data.selected_section_id || 0;
                state.currentSection = data.current_section || null;
                state.sessions = data.sessions || [];
                state.selectedSessionId = data.selected_session_id || 0;
                state.selectedSession = data.selected_session || null;
                state.students = data.students || [];

                renderReportsPage(data);
            })
            .catch(err => {
                console.error("Failed to load reports data:", err);
            });
    }

    function renderReportsPage(data) {
        // User profile & topbar
        if (data.user) {
            const u = data.user;
            setText("sidebarAvatar", u.avatar_initials || "CP");
            setText("topbarAvatar", u.avatar_initials || "CP");
            setText("sidebarUserName", `Prof. ${u.name}`);
            setText("sidebarFacultyId", `Faculty ID ${u.faculty_id}`);
            setText("topbarName", u.name);
            setText("topbarRole", u.title || "Professor");
        }
        setText("topbarDate", data.today_date);

        // 1. Render Section Schedule Cards
        renderSectionCards();

        // Show panel ONLY if user explicitly clicked a card
        const detailPanel = document.getElementById("reportDetailPanel");
        if (detailPanel) {
            if (state.sectionClicked && state.selectedSectionId > 0 && state.currentSection) {
                detailPanel.style.display = "block";
                detailPanel.style.animation = "fadeInUp 0.3s ease";
            } else if (!state.sectionClicked) {
                detailPanel.style.display = "none";
            }
        }

        // 2. Render Current Section Header & Session dropdown
        renderSectionMeta();

        // 3. Render Statistics Summary
        renderStats(data.stats);

        // 4. Render Alphabetical Student Table
        renderStudentTable();
    }

    function renderSectionCards() {
        const container = document.getElementById("sectionCardsContainer");
        if (!container) return;

        if (state.sections.length === 0) {
            container.innerHTML = `<div style="color: #64748b; font-size: 13.5px; padding: 12px;">No active sections available.</div>`;
            return;
        }

        container.innerHTML = state.sections.map(sec => {
            const isActive = sec.id == state.selectedSectionId;
            const timeStr = `${formatTime(sec.start_time)} - ${formatTime(sec.end_time)}`;

            return `
                <div class="section-card-item ${isActive ? 'active' : ''}" data-id="${sec.id}">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <strong style="font-size: 16px; color: #0f172a;">${escapeHtml(sec.section_name)}</strong>
                        <span style="font-size: 11.5px; font-weight: 700; color: #2563eb; background: #eff6ff; padding: 2px 8px; border-radius: 6px;">
                            ${escapeHtml(sec.subject_code)}
                        </span>
                    </div>
                    <div style="font-size: 12.5px; color: #475569; font-weight: 500;">
                        ${escapeHtml(sec.subject_name)}
                    </div>
                    <div style="margin-top: 6px; padding-top: 8px; border-top: 1px dashed #e2e8f0; font-size: 12px; color: #64748b; display: flex; flex-direction: column; gap: 3px;">
                        <div>📅 <strong>${escapeHtml(sec.schedule_day)}</strong> &middot; 🕒 ${timeStr}</div>
                        <div>📍 Room: <strong>${escapeHtml(sec.room || 'TBA')}</strong> &middot; 👥 ${sec.student_count} Students</div>
                    </div>
                </div>
            `;
        }).join("");

        // Bind click on section cards
        container.querySelectorAll(".section-card-item").forEach(card => {
            card.addEventListener("click", () => {
                const secId = parseInt(card.dataset.id, 10);
                state.sectionClicked = true;  // user explicitly clicked
                if (secId !== state.selectedSectionId) {
                    loadReportsData(secId, 0);
                } else {
                    // Already active — just reveal the panel
                    const panel = document.getElementById("reportDetailPanel");
                    if (panel) {
                        panel.style.display = "block";
                        panel.style.animation = "fadeInUp 0.3s ease";
                    }
                }
            });
        });
    }

    function renderSectionMeta() {
        const sec = state.currentSection;
        if (!sec) return;

        setText("currentSectionTitle", sec.section_name);
        setText("currentSubjectCode", `${sec.subject_code} · ${sec.subject_name}`);

        const timeStr = `${formatTime(sec.start_time)} - ${formatTime(sec.end_time)}`;
        const detailsEl = document.getElementById("currentScheduleDetails");
        if (detailsEl) {
            detailsEl.innerHTML = `
                <span>📅 <strong>${escapeHtml(sec.schedule_day)}</strong></span>
                <span>&bull;</span>
                <span>🕒 <strong>${timeStr}</strong></span>
                <span>&bull;</span>
                <span>📍 Room: <strong>${escapeHtml(sec.room)}</strong></span>
            `;
        }

        // Sessions Dropdown
        const sessDropdown = document.getElementById("sessionSelectDropdown");
        if (sessDropdown) {
            if (state.sessions.length > 0) {
                sessDropdown.innerHTML = state.sessions.map(s => {
                    const sel = s.id == state.selectedSessionId ? "selected" : "";
                    const sTime = `${formatTime(s.start_time)} - ${formatTime(s.end_time)}`;
                    return `<option value="${s.id}" ${sel}>${s.session_date} (${sTime})</option>`;
                }).join("");
            } else {
                sessDropdown.innerHTML = `<option value="0">No sessions recorded yet</option>`;
            }

            sessDropdown.onchange = (e) => {
                const newSessId = parseInt(e.target.value, 10);
                loadReportsData(state.selectedSectionId, newSessId);
            };
        }
    }

    function renderStats(stats) {
        if (!stats) return;
        setText("statTotalStudents", stats.total);
        setText("statPresentStudents", stats.present);
        setText("statLateStudents", stats.late);
        setText("statAbsentStudents", stats.absent);
        setText("statAttendanceRate", `${stats.rate}%`);
    }

    function renderStudentTable() {
        const tbody = document.getElementById("reportsTableBody");
        if (!tbody) return;

        const filtered = state.students.filter(stu => {
            const st = (stu.attendance_status || "absent").toLowerCase();
            if (state.filterStatus !== "all" && st !== state.filterStatus) {
                return false;
            }

            if (state.searchQuery) {
                const q = state.searchQuery.toLowerCase();
                const fullName = `${stu.last_name} ${stu.first_name}`.toLowerCase();
                const num = (stu.student_number || "").toLowerCase();
                const em = (stu.email || "").toLowerCase();
                if (!fullName.includes(q) && !num.includes(q) && !em.includes(q)) {
                    return false;
                }
            }

            return true;
        });

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="empty-state-cell">No students match the current filter or search query.</td></tr>`;
            return;
        }

        tbody.innerHTML = filtered.map((stu, idx) => {
            const st = (stu.attendance_status || "absent").toLowerCase();
            let badgeClass = "status-absent";
            let badgeText = "Absent";

            if (st === "present") {
                badgeClass = "status-present";
                badgeText = "Present";
            } else if (st === "late") {
                badgeClass = "status-late";
                badgeText = "Late";
            }

            const scanTime = stu.scanned_at ? formatDateTime(stu.scanned_at) : "<span style='color: #94a3b8;'>--:--</span>";

            let uniformPhotoHtml = '';
            if (stu.photo_url) {
                uniformPhotoHtml = `
                    <button type="button" class="view-uniform-btn" data-student-id="${stu.student_id}" title="Click to view uniform photo"
                            style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 8px; border: 1.5px solid #c7d2fe; background: #eef2ff; color: #4338ca; font-size: 11.5px; font-weight: 700; cursor: pointer; transition: all 0.15s;">
                        <img src="../../${escapeHtml(stu.photo_url)}" style="width: 22px; height: 22px; border-radius: 5px; object-fit: cover; border: 1px solid #a5b4fc;">
                        <span>👔 View Photo</span>
                    </button>
                `;
            } else {
                uniformPhotoHtml = `<span style="font-size: 11.5px; color: #94a3b8; font-style: italic;">No photo</span>`;
            }

            return `
                <tr class="student-report-row" data-id="${stu.student_id}">
                    <td style="padding: 16px; font-size: 13px; font-weight: 600; color: #94a3b8;">
                        ${idx + 1}
                    </td>
                    <td style="padding: 16px;">
                        <strong style="font-size: 14px; color: #0f172a;">${escapeHtml(stu.last_name)}, ${escapeHtml(stu.first_name)}</strong>
                    </td>
                    <td style="padding: 16px; font-size: 13px; font-weight: 600; color: #2563eb; font-family: monospace;">
                        ${escapeHtml(stu.student_number)}
                    </td>
                    <td style="padding: 16px; font-size: 13px; color: #475569;">
                        ${escapeHtml(stu.email || 'N/A')}
                    </td>
                    <td style="padding: 16px;">
                        <span class="status-pill ${badgeClass}">
                            &bull; ${badgeText}
                        </span>
                    </td>
                    <td style="padding: 16px; font-size: 12.5px; color: #475569;">
                        ${scanTime}
                    </td>
                    <td style="padding: 16px;">
                        ${uniformPhotoHtml}
                    </td>
                    <td style="padding: 16px;">
                        <select class="quick-status-adjust modal-select" data-student-id="${stu.student_id}" style="height: 32px; padding: 0 8px; font-size: 12px; max-width: 110px;">
                            <option value="present" ${st === 'present' ? 'selected' : ''}>Present</option>
                            <option value="late" ${st === 'late' ? 'selected' : ''}>Late</option>
                            <option value="absent" ${st === 'absent' ? 'selected' : ''}>Absent</option>
                        </select>
                    </td>
                </tr>
            `;
        }).join("");

        // Bind quick status adjust
        tbody.querySelectorAll(".quick-status-adjust").forEach(sel => {
            sel.addEventListener("change", (e) => {
                const sId = sel.dataset.studentId;
                const newStatus = sel.value;
                updateStudentStatus(sId, newStatus);
            });
        });

        // Bind View Uniform photo buttons
        tbody.querySelectorAll(".view-uniform-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                const sId = parseInt(btn.dataset.studentId, 10);
                const s = state.students.find(x => parseInt(x.student_id, 10) === sId);
                if (s && s.photo_url) {
                    openUniformPhotoModal(s);
                }
            });
        });
    }

    let activeModalStudent = null;
    function openUniformPhotoModal(stu) {
        activeModalStudent = stu;
        const modal = document.getElementById("uniformPhotoModal");
        if (!modal) return;

        document.getElementById("modalStudentName").textContent = `${stu.last_name}, ${stu.first_name}`;
        document.getElementById("modalStudentNum").textContent = stu.student_number;
        document.getElementById("modalPhotoImage").src = "../../" + stu.photo_url;

        const st = (stu.attendance_status || "present").toLowerCase();
        let badgeBg = st === "present" ? "#dcfce7" : st === "late" ? "#fef3c7" : "#fee2e2";
        let badgeColor = st === "present" ? "#15803d" : st === "late" ? "#b45309" : "#dc2626";
        document.getElementById("modalStatusBadge").innerHTML = `
            <span style="background:${badgeBg};color:${badgeColor};font-size:11px;font-weight:700;padding:4px 8px;border-radius:6px;text-transform:capitalize;">
                ${st}
            </span>
        `;

        modal.style.display = "flex";
    }

    // Close Photo Modal events
    document.getElementById("closePhotoModalBtn")?.addEventListener("click", () => {
        const modal = document.getElementById("uniformPhotoModal");
        if (modal) modal.style.display = "none";
    });
    document.getElementById("uniformPhotoModal")?.addEventListener("click", (e) => {
        if (e.target.id === "uniformPhotoModal") {
            e.target.style.display = "none";
        }
    });

    // Quick status adjust inside modal
    document.querySelectorAll(".btn-modal-action").forEach(btn => {
        btn.addEventListener("click", () => {
            if (!activeModalStudent) return;
            const newStatus = btn.dataset.status;
            updateStudentStatus(activeModalStudent.student_id, newStatus);
            const modal = document.getElementById("uniformPhotoModal");
            if (modal) modal.style.display = "none";
        });
    });

    function updateStudentStatus(studentId, newStatus) {
        if (!state.selectedSessionId) {
            showAlert("No active session selected to record attendance for.", "error");
            return;
        }

        const formData = new FormData();
        formData.append("action", "update_student_status");
        formData.append("session_id", state.selectedSessionId);
        formData.append("student_id", studentId);
        formData.append("status", newStatus);

        fetch("../../conf/php/reports_data.php", {
            method: "POST",
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                showAlert("Student attendance updated!", "success");
                loadReportsData(state.selectedSectionId, state.selectedSessionId);
            } else {
                showAlert(res.message, "error");
            }
        })
        .catch(err => {
            console.error("Error updating status:", err);
            showAlert("Server error updating attendance", "error");
        });
    }

    // Search filter event
    const searchInput = document.getElementById("studentSearchFilter");
    const topSearch = document.getElementById("topSearchInput");

    if (searchInput) {
        searchInput.addEventListener("input", (e) => {
            state.searchQuery = e.target.value.trim();
            renderStudentTable();
        });
    }

    if (topSearch) {
        topSearch.addEventListener("input", (e) => {
            state.searchQuery = e.target.value.trim();
            renderStudentTable();
        });
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

    // Status filter buttons
    const filterGroup = document.getElementById("statusFilterButtonGroup");
    if (filterGroup) {
        filterGroup.querySelectorAll("button").forEach(btn => {
            btn.addEventListener("click", () => {
                filterGroup.querySelectorAll("button").forEach(b => {
                    b.style.background = "#f8fafc";
                    b.style.color = "var(--text-body)";
                    b.style.borderColor = "#e2e8f0";
                });
                btn.style.background = "#2563eb";
                btn.style.color = "#ffffff";
                btn.style.borderColor = "#2563eb";

                state.filterStatus = btn.dataset.status;
                renderStudentTable();
            });
        });
    }

    // Top Download CSV Button & Modal Controller
    const downloadCsvBtn = document.getElementById("downloadCsvBtn");
    const exportModal = document.getElementById("exportCsvModal");
    const closeExportBtn = document.getElementById("closeExportCsvModalBtn");
    const cancelExportBtn = document.getElementById("cancelExportCsvBtn");
    const exportForm = document.getElementById("exportCsvForm");
    const exportSecSelect = document.getElementById("exportSectionSelect");
    const covAutoRadio = document.getElementById("covAuto");
    const covCustomRadio = document.getElementById("covCustom");
    const customDateBox = document.getElementById("customDateRangeBox");
    const exportStartInput = document.getElementById("exportStartDate");
    const exportEndInput = document.getElementById("exportEndDate");

    if (downloadCsvBtn) {
        downloadCsvBtn.addEventListener("click", () => {
            openExportModal();
        });
    }

    function openExportModal() {
        if (!exportModal) return;

        // Populate sections
        if (exportSecSelect && state.sections && state.sections.length > 0) {
            exportSecSelect.innerHTML = state.sections.map(s => `
                <option value="${s.id}" ${s.id == state.selectedSectionId ? 'selected' : ''}>
                    ${escapeHtml(s.section_name)} &middot; ${escapeHtml(s.subject_code)} (${escapeHtml(s.subject_name)})
                </option>
            `).join("");
        }

        // Set default dates
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const d = String(now.getDate()).padStart(2, '0');
        const todayStr = `${y}-${m}-${d}`;
        
        if (exportStartInput && !exportStartInput.value) {
            exportStartInput.value = `${y}-${m}-01`;
        }
        if (exportEndInput && !exportEndInput.value) {
            exportEndInput.value = todayStr;
        }

        // Reset to auto coverage
        if (covAutoRadio) covAutoRadio.checked = true;
        if (customDateBox) customDateBox.style.display = "none";

        exportModal.classList.remove("hidden");
    }

    if (closeExportBtn) closeExportBtn.addEventListener("click", () => exportModal.classList.add("hidden"));
    if (cancelExportBtn) cancelExportBtn.addEventListener("click", () => exportModal.classList.add("hidden"));

    if (covAutoRadio && covCustomRadio && customDateBox) {
        covAutoRadio.addEventListener("change", () => {
            if (covAutoRadio.checked) customDateBox.style.display = "none";
        });
        covCustomRadio.addEventListener("change", () => {
            if (covCustomRadio.checked) customDateBox.style.display = "grid";
        });
    }

    if (exportForm) {
        exportForm.addEventListener("submit", (e) => {
            e.preventDefault();

            const secId = exportSecSelect ? exportSecSelect.value : state.selectedSectionId;
            const termSelect = document.getElementById("exportTermSelect");
            const term = termSelect ? termSelect.value : "prelim";
            const covType = covCustomRadio && covCustomRadio.checked ? "custom" : "auto";
            const startDate = exportStartInput ? exportStartInput.value : "";
            const endDate = exportEndInput ? exportEndInput.value : "";
            const formatSelect = document.getElementById("exportStatusFormat");
            const statusFmt = formatSelect ? formatSelect.value : "full";

            if (!secId || secId <= 0) {
                showAlert("Please select a valid section.", "error");
                return;
            }

            const downloadUrl = `../../conf/php/export_report.php?section_id=${encodeURIComponent(secId)}&term=${encodeURIComponent(term)}&coverage_type=${encodeURIComponent(covType)}&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&status_format=${encodeURIComponent(statusFmt)}`;

            exportModal.classList.add("hidden");
            showAlert(`Generating ${term.toUpperCase()} attendance report... Download will begin shortly.`, "success");

            // Trigger direct file download
            window.location.href = downloadUrl;
        });
    }

    // Top Print Report Button
    const printBtn = document.getElementById("printReportBtn");
    if (printBtn) {
        printBtn.addEventListener("click", () => {
            window.print();
        });
    }

    // Helpers
    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text !== null && text !== undefined ? text : "";
    }

    function formatTime(timeStr) {
        if (!timeStr) return "--:--";
        const parts = timeStr.split(":");
        let h = parseInt(parts[0], 10);
        const m = parts[1] || "00";
        if (isNaN(h)) return timeStr;
        const ampm = h >= 12 ? "PM" : "AM";
        h = h % 12;
        h = h ? h : 12;
        return `${h}:${m} ${ampm}`;
    }

    function formatDateTime(dtStr) {
        if (!dtStr) return "--:--";
        const d = new Date(dtStr);
        if (isNaN(d.getTime())) return dtStr;
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
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

    function escapeHtml(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }
});
