// Attendance QR Sessions Client Logic
document.addEventListener("DOMContentLoaded", () => {
    loadSessions();

    function loadSessions() {
        fetch("../../conf/php/attendance_sessions_data.php")
            .then(res => {
                if (res.status === 401) {
                    window.location.href = "login.html";
                    return null;
                }
                return res.json();
            })
            .then(data => {
                if (!data || !data.success) return;
                renderSessions(data);
            })
            .catch(err => {
                console.error("Failed to load sessions:", err);
            });
    }

    function renderSessions(data) {
        if (data.user) {
            const u = data.user;
            const sbAvatar = document.getElementById("sidebarAvatar");
            const sbName = document.getElementById("sidebarUserName");
            const sbId = document.getElementById("sidebarFacultyId");
            const tbAvatar = document.getElementById("topbarAvatar");
            const tbName = document.getElementById("topbarName");
            const tbRole = document.getElementById("topbarRole");

            if (sbAvatar) sbAvatar.textContent = u.avatar_initials || "CP";
            if (sbName) sbName.textContent = `Prof. ${u.name}`;
            if (sbId) sbId.textContent = `Faculty ID ${u.faculty_id}`;
            if (tbAvatar) tbAvatar.textContent = u.avatar_initials || "CP";
            if (tbName) tbName.textContent = u.name;
            if (tbRole) tbRole.textContent = u.title || "Professor";
        }

        // Store detected server LAN IP (e.g. 192.168.1.3) for mobile QR scanning
        window.serverLanIp = data.lan_ip || "";

        const topDateEl = document.getElementById("topbarDate");
        if (topDateEl) topDateEl.textContent = data.today_date;

        // Modal Section Select
        const sel = document.getElementById("modalSectionSelect");
        if (sel && data.sections) {
            sel.innerHTML = data.sections.map(sec => `
                <option value="${sec.id}">${escapeHtml(sec.section_name)} &middot; ${escapeHtml(sec.subject_name)}</option>
            `).join("");
        }

        const tbody = document.getElementById("sessionsTableBody");
        if (tbody) {
            if (data.sessions && data.sessions.length > 0) {
                tbody.innerHTML = data.sessions.map(s => {
                    const timeRange = `${formatTime(s.start_time)} - ${formatTime(s.end_time)}`;
                    const statusInfo = getSessionStatusInfo(s);

                    return `
                        <tr class="session-row" id="session-row-${s.id}"
                            data-id="${s.id}"
                            data-date="${escapeHtml(s.session_date)}"
                            data-start="${escapeHtml(s.start_time || '00:00:00')}"
                            data-end="${escapeHtml(s.end_time || '23:59:59')}"
                            data-status="${escapeHtml(s.status || 'active')}">
                            <td style="padding: 18px 16px; font-size: 13.5px; font-weight: 700; color: #0f172a;">
                                ${escapeHtml(s.section_name)}
                            </td>
                            <td style="padding: 18px 16px; font-size: 13.5px; color: #334155; font-weight: 500;">
                                ${escapeHtml(s.subject_name)}
                            </td>
                            <td style="padding: 18px 16px; font-size: 13.5px; color: #334155; white-space: nowrap;">
                                ${escapeHtml(s.session_date)}
                            </td>
                            <td style="padding: 18px 16px; font-size: 13px; color: #64748b; white-space: nowrap;">
                                <span class="session-status-dot" id="dot-${s.id}" style="display: inline-block; width: 8.5px; height: 8.5px; border-radius: 50%; background: ${statusInfo.color}; margin-right: 7px; box-shadow: ${statusInfo.shadow}; transition: all 0.3s ease;" title="${statusInfo.title}"></span>${timeRange}
                            </td>
                            <td style="padding: 18px 16px; font-size: 13.5px; font-weight: 700; color: #334155;">
                                ${s.present_count}
                            </td>
                            <td style="padding: 18px 16px;">
                                <a href="#" class="view-qr-link" 
                                   data-section="${escapeHtml(s.section_name)}"
                                   data-subject="${escapeHtml(s.subject_name)}"
                                   data-token="${escapeHtml(s.qr_token)}"
                                   style="color: #2563eb; font-weight: 700; text-decoration: none; font-size: 13.5px; display: inline-flex; align-items: center; gap: 4px;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <rect width="7" height="7" x="3" y="3" rx="1"/>
                                        <rect width="7" height="7" x="14" y="3" rx="1"/>
                                        <rect width="7" height="7" x="14" y="14" rx="1"/>
                                        <rect width="7" height="7" x="3" y="14" rx="1"/>
                                    </svg>
                                    View QR
                                </a>
                            </td>
                            <td style="padding: 18px 16px;">
                                <div class="table-actions-group">
                                    <button type="button" class="btn-action-edit edit-session-btn"
                                        data-id="${s.id}"
                                        data-section="${escapeHtml(s.section_name)}"
                                        data-subject="${escapeHtml(s.subject_name)}"
                                        data-date="${escapeHtml(s.session_date)}"
                                        data-start="${escapeHtml(s.start_time || '08:00:00')}"
                                        data-end="${escapeHtml(s.end_time || '09:30:00')}"
                                        data-status="${escapeHtml(s.status || 'active')}"
                                        data-late="${s.late_threshold_minutes || 15}"
                                        title="Edit date and time">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                        <span>Edit</span>
                                    </button>
                                    <button type="button" class="btn-action-delete delete-session-btn"
                                        data-id="${s.id}"
                                        data-section="${escapeHtml(s.section_name)}"
                                        data-date="${escapeHtml(s.session_date)}"
                                        title="Delete QR session">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                        <span>Delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                }).join("");

                bindViewQrEvents();
                bindActionEvents();
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="empty-state-cell">No attendance sessions found. Click "+ Generate QR" to create one.</td></tr>`;
            }
        }
    }

    function bindViewQrEvents() {
        const viewModal = document.getElementById("viewQrModal");
        const viewSectionTitle = document.getElementById("viewQrSectionTitle");
        const viewSubjectSubtitle = document.getElementById("viewQrSubjectSubtitle");
        const viewTokenText = document.getElementById("viewQrTokenText");
        const viewQrImg = document.getElementById("viewQrImage");

        document.querySelectorAll(".view-qr-link").forEach(link => {
            link.addEventListener("click", (e) => {
                e.preventDefault();
                const sec  = link.getAttribute("data-section");
                const subj = link.getAttribute("data-subject");
                const tok  = link.getAttribute("data-token");

                // Build the clean student check-in URL using Apache rewrite (/checkin/TOKEN)
                // If accessed via localhost, use detected LAN IP (e.g. 192.168.1.3) so phones scanning via Chrome can connect!
                const isLocalhost = (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1');
                const lanIp = window.serverLanIp || window.location.hostname;
                const networkHost = (isLocalhost && lanIp) ? `${lanIp}${window.location.port ? ':' + window.location.port : ''}` : window.location.host;
                const checkinUrl = `${window.location.protocol}//${networkHost}/SmartStudent_Attendance/checkin/${encodeURIComponent(tok)}`;

                if (viewSectionTitle) viewSectionTitle.textContent = `${sec} — Attendance QR`;
                if (viewSubjectSubtitle) viewSubjectSubtitle.textContent = subj;
                if (viewTokenText) {
                    if (viewTokenText.tagName === "INPUT") {
                        viewTokenText.value = checkinUrl;
                    } else {
                        viewTokenText.textContent = checkinUrl;
                    }
                }

                if (viewQrImg) {
                    // QR encodes the clean reachable network URL — Chrome on phones connects successfully!
                    viewQrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=10&data=${encodeURIComponent(checkinUrl)}`;
                }

                if (viewModal) viewModal.classList.remove("hidden");
            });
        });
    }

    function bindActionEvents() {
        // Edit button click
        const editModal = document.getElementById("editQrModal");
        const editIdInput = document.getElementById("editSessionId");
        const editSecDisplay = document.getElementById("editSectionDisplay");
        const editDateInput = document.getElementById("editSessionDate");
        const editStartInput = document.getElementById("editStartTime");
        const editEndInput = document.getElementById("editEndTime");
        const editStatusSelect = document.getElementById("editSessionStatus");
        const editLateInput = document.getElementById("editLateThreshold");
        const editLatePreview = document.getElementById("editLatePreviewText");

        if (editLateInput && editLatePreview) {
            editLateInput.addEventListener("input", () => {
                const val = Math.max(0, parseInt(editLateInput.value, 10) || 0);
                editLatePreview.textContent = `After ${val} mins = Late`;
            });
        }

        document.querySelectorAll(".edit-session-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                const now = new Date();
                const pad = (n) => String(n).padStart(2, "0");
                const todayStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;

                if (editIdInput) editIdInput.value = btn.dataset.id;
                if (editSecDisplay) editSecDisplay.textContent = `${btn.dataset.section} · ${btn.dataset.subject}`;
                
                // If previous date is in the past, auto-update to today so the newly scheduled time works today
                if (editDateInput) {
                    editDateInput.value = (!btn.dataset.date || btn.dataset.date < todayStr) ? todayStr : btn.dataset.date;
                }
                if (editStartInput) editStartInput.value = (btn.dataset.start || "08:00").substring(0, 5);
                if (editEndInput) editEndInput.value = (btn.dataset.end || "09:30").substring(0, 5);
                
                // Populate late threshold
                const lateMins = btn.dataset.late || "15";
                if (editLateInput) editLateInput.value = lateMins;
                if (editLatePreview) editLatePreview.textContent = `After ${lateMins} mins = Late`;

                // Default status to 'active' so setting new hours activates the session
                if (editStatusSelect) {
                    editStatusSelect.value = "active";
                }

                if (editModal) editModal.classList.remove("hidden");
            });
        });

        // Delete button click
        document.querySelectorAll(".delete-session-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                const sid = btn.dataset.id;
                const sec = btn.dataset.section;
                const sdate = btn.dataset.date;

                if (!confirm(`Are you sure you want to delete the QR session for "${sec}" on ${sdate}?`)) {
                    return;
                }

                const formData = new FormData();
                formData.append("action", "delete_session");
                formData.append("session_id", sid);

                fetch("../../conf/php/attendance_sessions_data.php", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showAlert(res.message, "success");
                        loadSessions();
                    } else {
                        showAlert(res.message, "error");
                    }
                })
                .catch(err => {
                    console.error("Error deleting session:", err);
                    showAlert("Server error deleting session", "error");
                });
            });
        });
    }

    // Modal: Generate QR
    const genModal = document.getElementById("generateQrModal");
    const openGenBtn = document.getElementById("openGenerateModalBtn");
    const closeGenBtn = document.getElementById("closeGenerateModalBtn");
    const cancelGenBtn = document.getElementById("cancelGenerateBtn");
    const genForm = document.getElementById("generateQrForm");

    if (openGenBtn) {
        openGenBtn.addEventListener("click", () => {
            // Set default date and time
            const now = new Date();
            const pad = (n) => String(n).padStart(2, "0");
            const todayStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
            const curTime = `${pad(now.getHours())}:${pad(now.getMinutes())}`;
            const end = new Date(now.getTime() + 90 * 60000);
            const endTime = `${pad(end.getHours())}:${pad(end.getMinutes())}`;

            const dateInput = document.getElementById("modalSessionDate");
            const startInput = document.getElementById("modalStartTime");
            const endInput = document.getElementById("modalEndTime");
            const lateInput = document.getElementById("modalLateThreshold");
            const latePreview = document.getElementById("modalLatePreviewText");

            if (dateInput) dateInput.value = todayStr;
            if (startInput) startInput.value = curTime;
            if (endInput) endInput.value = endTime;
            if (lateInput) {
                lateInput.value = "15";
                if (latePreview) latePreview.textContent = "After 15 mins = Late";
                lateInput.oninput = () => {
                    const val = Math.max(0, parseInt(lateInput.value, 10) || 0);
                    if (latePreview) latePreview.textContent = `After ${val} mins = Late`;
                };
            }

            genModal.classList.remove("hidden");
        });
    }

    if (closeGenBtn) closeGenBtn.addEventListener("click", () => genModal.classList.add("hidden"));
    if (cancelGenBtn) cancelGenBtn.addEventListener("click", () => genModal.classList.add("hidden"));

    if (genForm) {
        genForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const formData = new FormData(genForm);

            fetch("../../conf/php/attendance_sessions_data.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    genModal.classList.add("hidden");
                    showAlert(res.message, "success");
                    loadSessions();
                } else {
                    showAlert(res.message, "error");
                }
            })
            .catch(err => {
                console.error("Error generating QR:", err);
                showAlert("Server error generating QR session", "error");
            });
        });
    }

    // Modal: Edit QR
    const editModal = document.getElementById("editQrModal");
    const closeEditBtn = document.getElementById("closeEditModalBtn");
    const cancelEditBtn = document.getElementById("cancelEditBtn");
    const editForm = document.getElementById("editQrForm");

    if (closeEditBtn) closeEditBtn.addEventListener("click", () => editModal.classList.add("hidden"));
    if (cancelEditBtn) cancelEditBtn.addEventListener("click", () => editModal.classList.add("hidden"));

    if (editForm) {
        editForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);

            fetch("../../conf/php/attendance_sessions_data.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    editModal.classList.add("hidden");
                    showAlert(res.message, "success");
                    loadSessions();
                } else {
                    showAlert(res.message, "error");
                }
            })
            .catch(err => {
                console.error("Error updating session:", err);
                showAlert("Server error updating QR session", "error");
            });
        });
    }

    // Modal: View QR Close
    const viewModal = document.getElementById("viewQrModal");
    const closeViewBtn = document.getElementById("closeViewQrModalBtn");
    const doneViewBtn  = document.getElementById("doneViewQrBtn");
    if (closeViewBtn) closeViewBtn.addEventListener("click", () => viewModal.classList.add("hidden"));
    if (doneViewBtn)  doneViewBtn.addEventListener("click",  () => viewModal.classList.add("hidden"));

    // Copy QR URL to clipboard
    window.copyQrUrl = function () {
        const urlText = document.getElementById("viewQrTokenText")?.textContent || "";
        if (!urlText || urlText === "—") return;
        navigator.clipboard.writeText(urlText).then(() => {
            const btn = document.getElementById("copyQrUrlBtn");
            if (btn) {
                btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>';
                setTimeout(() => {
                    btn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2.5"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>';
                }, 2000);
            }
            showAlert("Check-in URL copied to clipboard!", "success");
        }).catch(() => {
            // Fallback for older browsers
            const el = document.createElement("textarea");
            el.value = urlText;
            document.body.appendChild(el);
            el.select();
            document.execCommand("copy");
            document.body.removeChild(el);
            showAlert("URL copied!", "success");
        });
    };


    // Search filter
    const topSearch = document.getElementById("topSearchInput");
    if (topSearch) {
        topSearch.addEventListener("input", (e) => {
            const q = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll("#sessionsTableBody tr.session-row");
            rows.forEach(r => {
                r.style.display = r.textContent.toLowerCase().includes(q) ? "" : "none";
            });
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
    function getSessionStatusInfo(s) {
        if (s.status === 'completed') {
            return {
                color: '#ef4444',
                shadow: '0 0 0 2px rgba(239, 68, 68, 0.2)',
                title: 'Inactive (Session Completed / Closed)',
                isActive: false
            };
        }

        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const todayStr = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;

        // If date has passed -> inactive (red)
        if (s.session_date < todayStr) {
            return {
                color: '#ef4444',
                shadow: '0 0 0 2px rgba(239, 68, 68, 0.2)',
                title: 'Inactive (Past Date)',
                isActive: false
            };
        }

        // If scheduled for a future date -> scheduled (amber)
        if (s.session_date > todayStr) {
            return {
                color: '#f59e0b',
                shadow: '0 0 0 2px rgba(245, 158, 11, 0.25)',
                title: `Scheduled for ${s.session_date} at ${formatTime(s.start_time)}`,
                isActive: false
            };
        }

        // Date is today! Parse start and end time with seconds precision
        const startParts = (s.start_time || '00:00:00').split(':');
        const endParts   = (s.end_time || '23:59:59').split(':');

        const startSec = parseInt(startParts[0], 10) * 3600 + parseInt(startParts[1] || '0', 10) * 60 + parseInt(startParts[2] || '0', 10);
        const endSec   = parseInt(endParts[0], 10) * 3600 + parseInt(endParts[1] || '0', 10) * 60 + parseInt(endParts[2] || '0', 10);
        const nowSec   = now.getHours() * 3600 + now.getMinutes() * 60 + now.getSeconds();

        if (nowSec < startSec) {
            // Not yet start time (e.g. set 1:20 AM, now is 1:19 AM)
            return {
                color: '#f59e0b',
                shadow: '0 0 0 2px rgba(245, 158, 11, 0.3)',
                title: `Scheduled (Opens at ${formatTime(s.start_time)})`,
                isActive: false
            };
        } else if (nowSec >= startSec && nowSec <= endSec) {
            // Active right now: 1:20 AM has arrived!
            return {
                color: '#16a34a',
                shadow: '0 0 0 3px rgba(22, 163, 74, 0.35)',
                title: `Active (Class in session until ${formatTime(s.end_time)})`,
                isActive: true
            };
        } else {
            // Ended
            return {
                color: '#ef4444',
                shadow: '0 0 0 2px rgba(239, 68, 68, 0.2)',
                title: `Inactive (Ended at ${formatTime(s.end_time)})`,
                isActive: false
            };
        }
    }

    function isSessionActive(s) {
        return getSessionStatusInfo(s).isActive;
    }

    function updateLiveStatusDots() {
        document.querySelectorAll(".session-row").forEach(row => {
            const dot = row.querySelector(".session-status-dot");
            if (!dot) return;
            const s = {
                session_date: row.dataset.date,
                start_time: row.dataset.start,
                end_time: row.dataset.end,
                status: row.dataset.status
            };
            const info = getSessionStatusInfo(s);
            dot.style.background = info.color;
            dot.style.boxShadow = info.shadow;
            dot.title = info.title;
        });
    }

    // Live update check every 3 seconds so the dot turns GREEN the exact second 1:20:00 AM arrives!
    setInterval(updateLiveStatusDots, 3000);

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
