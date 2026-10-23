// Sections Page Client Logic
document.addEventListener("DOMContentLoaded", () => {
    let sectionsDataStore = [];
    loadSections();

    // 1. Fetch Sections from Backend
    function loadSections() {
        fetch("../../conf/php/sections_data.php")
            .then(res => {
                if (res.status === 401) {
                    window.location.href = "login.html";
                    return null;
                }
                return res.json();
            })
            .then(data => {
                if (!data || !data.success) return;
                sectionsDataStore = data.sections || [];
                renderSections(data);
            })
            .catch(err => {
                console.error("Failed to load sections:", err);
            });
    }

    function renderSections(data) {
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

        const snapshotEl = document.getElementById("snapshotTimestamp");
        if (snapshotEl) snapshotEl.textContent = data.snapshot_time;

        const dateEl = document.getElementById("topbarDate");
        if (dateEl) dateEl.textContent = data.today_date;

        const countEl = document.getElementById("sectionsCountText");
        if (countEl) countEl.textContent = `${data.total_sections} sections`;

        const studentsEl = document.getElementById("studentsCountText");
        if (studentsEl) studentsEl.textContent = `${data.total_students} registered students`;

        const tbody = document.getElementById("sectionsTableBody");
        if (tbody) {
            if (data.sections && data.sections.length > 0) {
                tbody.innerHTML = data.sections.map(s => {
                    const days = (s.schedule_day || 'Not set').split(',').map(d => d.trim()).filter(Boolean);
                    const daysHtml = days.map(d => `
                        <span class="schedule-badge-pill" style="font-size: 11px; padding: 2px 8px;">
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <span>${escapeHtml(d)}</span>
                        </span>
                    `).join('');

                    return `
                    <tr class="section-row" id="section-row-${s.id}">
                        <td style="padding: 16px; vertical-align: middle;">
                            <strong style="font-size: 14px; color: #0f172a;">${escapeHtml(s.section_name)}</strong>
                            <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                ${escapeHtml(s.subject_code)} &middot; ${escapeHtml(s.subject_name)}
                            </div>
                        </td>
                        <td style="padding: 16px; vertical-align: middle;">
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 4px; margin-bottom: 5px;">
                                ${daysHtml}
                            </div>
                            <div style="font-size: 12px; color: #475569; font-weight: 500; display: inline-flex; align-items: center; gap: 5px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <span>${formatTime(s.start_time)} – ${formatTime(s.end_time)}</span>
                                <span style="color: #cbd5e1;">&bull;</span>
                                <span style="color: #64748b;">${escapeHtml(s.room || 'Lab 304')}</span>
                            </div>
                        </td>
                        <td style="padding: 16px; font-size: 13.5px; font-weight: 600; color: #334155; vertical-align: middle;">
                            ${s.student_count}
                        </td>
                        <td style="padding: 16px; font-size: 13.5px; font-weight: 600; color: #334155; vertical-align: middle;">
                            ${s.active_sessions_count}
                        </td>
                        <td style="padding: 16px; vertical-align: middle;" class="actions-cell">
                            <div class="action-dropdown-wrapper">
                                <button type="button" class="action-dots-btn" data-id="${s.id}" title="Actions" aria-label="Section options">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                                        <circle cx="12" cy="5" r="2.2"/>
                                        <circle cx="12" cy="12" r="2.2"/>
                                        <circle cx="12" cy="19" r="2.2"/>
                                    </svg>
                                </button>
                                <div class="action-dropdown-menu" id="dropdown-${s.id}">
                                    <a href="section_details.html?id=${s.id}" class="action-item view-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <span>View</span>
                                    </a>
                                    <button type="button" class="action-item update-item" data-action="update" data-id="${s.id}">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                        </svg>
                                        <span>Update</span>
                                    </button>
                                    <button type="button" class="action-item delete-item" data-action="delete" data-id="${s.id}" data-name="${escapeHtml(s.section_name)}">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                            <line x1="10" y1="11" x2="10" y2="17"/>
                                            <line x1="14" y1="11" x2="14" y2="17"/>
                                        </svg>
                                        <span>Delete</span>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                    `;
                }).join("");
            } else {
                tbody.innerHTML = `<tr><td colspan="5" class="empty-state-cell">No sections registered yet. Click "+ Add Section" to create one.</td></tr>`;
            }
        }
    }

    // 2. Action Dropdowns (Fixed Viewport Positioning - Container Never Scrolls)
    document.addEventListener("click", (e) => {
        const dotsBtn = e.target.closest(".action-dots-btn");
        const actionItem = e.target.closest(".action-item");

        if (dotsBtn) {
            e.stopPropagation();
            const sectionId = dotsBtn.dataset.id;
            const targetMenu = document.getElementById(`dropdown-${sectionId}`);
            const wasShown = targetMenu && targetMenu.classList.contains("show");

            // Close all dropdowns first
            closeAllDropdowns();

            if (targetMenu && !wasShown) {
                targetMenu.classList.add("show");
                dotsBtn.classList.add("active");

                const rect = dotsBtn.getBoundingClientRect();
                const menuWidth = 145;
                const menuHeight = targetMenu.offsetHeight || 130;

                // Horizontal alignment (right-align with the 3-dots button)
                let left = rect.right - menuWidth;
                if (left < 10) left = 10;
                targetMenu.style.left = `${left}px`;

                // Vertical alignment: open upwards if close to window bottom
                if (rect.bottom + menuHeight > window.innerHeight - 15) {
                    targetMenu.style.top = `${rect.top - menuHeight - 4}px`;
                } else {
                    targetMenu.style.top = `${rect.bottom + 4}px`;
                }
            }
            return;
        }

        if (actionItem) {
            const action = actionItem.dataset.action;
            const id = parseInt(actionItem.dataset.id, 10);
            const name = actionItem.dataset.name || "";

            closeAllDropdowns();

            if (action === "update") {
                openEditSectionModal(id);
            } else if (action === "delete") {
                openDeleteSectionModal(id, name);
            }
            return;
        }

        // Clicked outside: close all dropdowns
        closeAllDropdowns();
    });

    function closeAllDropdowns() {
        document.querySelectorAll(".action-dropdown-menu.show").forEach(m => {
            m.classList.remove("show");
            m.style.left = "";
            m.style.top = "";
        });
        document.querySelectorAll(".action-dots-btn.active").forEach(b => b.classList.remove("active"));
    }

    // Close on any scroll or window resize
    window.addEventListener("scroll", closeAllDropdowns, true);
    window.addEventListener("resize", closeAllDropdowns);

    // 3. Edit Section Modal Logic
    const editModal = document.getElementById("editSectionModal");
    const editForm = document.getElementById("editSectionForm");
    const closeEditBtn = document.getElementById("closeEditSectionModal");
    const cancelEditBtn = document.getElementById("cancelEditSectionBtn");

    if (closeEditBtn) closeEditBtn.addEventListener("click", () => editModal.classList.add("hidden"));
    if (cancelEditBtn) cancelEditBtn.addEventListener("click", () => editModal.classList.add("hidden"));

    function openEditSectionModal(id) {
        const sec = sectionsDataStore.find(s => parseInt(s.id, 10) === id);
        if (!sec) return;

        document.getElementById("editSectionId").value = sec.id;
        document.getElementById("editSectionName").value = sec.section_name || "";
        document.getElementById("editSubjectCode").value = sec.subject_code || "";
        document.getElementById("editSubjectName").value = sec.subject_name || "";
        document.getElementById("editRoom").value = sec.room || "Lab 304";
        document.getElementById("editStartTime").value = sec.start_time ? sec.start_time.substring(0, 5) : "08:00";
        document.getElementById("editEndTime").value = sec.end_time ? sec.end_time.substring(0, 5) : "09:30";

        // Pre-select saved schedule days in the day picker
        const savedDays = (sec.schedule_day || '').split(',').map(d => d.trim().toLowerCase());
        const editGroup = document.getElementById('editDayPicker');
        const editHidden = document.getElementById('editScheduleDay');
        const editSummary = document.getElementById('editScheduleSummary');
        if (editGroup) {
            editGroup.querySelectorAll('.day-chip').forEach(chip => {
                const day = chip.dataset.day || '';
                if (savedDays.includes(day.toLowerCase())) {
                    chip.classList.add('active');
                } else {
                    chip.classList.remove('active');
                }
            });
            const selected = savedDays.filter(d => d).map(d => d.charAt(0).toUpperCase() + d.slice(1));
            if (editHidden) editHidden.value = sec.schedule_day || '';
            if (editSummary) {
                editSummary.innerHTML = selected.length
                    ? `Selected: <strong style="color:#2563eb">${selected.join(', ')}</strong>`
                    : '<span style="color:#94a3b8">No days selected</span>';
            }
        }

        editModal.classList.remove("hidden");
    }

    if (editForm) {
        editForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);

            fetch("../../conf/php/sections_data.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.conflict) {
                    showConflictModal(res.message);
                    return;
                }
                if (res.success) {
                    editModal.classList.add("hidden");
                    showAlert(res.message, "success");
                    loadSections(); // Refresh sections table
                } else {
                    showAlert(res.message, "error");
                }
            })
            .catch(err => {
                console.error("Error updating section:", err);
                showAlert("Server error updating section", "error");
            });
        });
    }

    // 4. Delete Section Modal Logic
    const deleteModal = document.getElementById("deleteSectionModal");
    const closeDeleteBtn = document.getElementById("closeDeleteSectionModal");
    const cancelDeleteBtn = document.getElementById("cancelDeleteSectionBtn");
    const confirmDeleteBtn = document.getElementById("confirmDeleteSectionBtn");

    if (closeDeleteBtn) closeDeleteBtn.addEventListener("click", () => deleteModal.classList.add("hidden"));
    if (cancelDeleteBtn) cancelDeleteBtn.addEventListener("click", () => deleteModal.classList.add("hidden"));

    function openDeleteSectionModal(id, name) {
        document.getElementById("deleteSectionId").value = id;
        document.getElementById("deleteSectionTargetName").textContent = name;
        deleteModal.classList.remove("hidden");
    }

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", () => {
            const id = document.getElementById("deleteSectionId").value;
            const formData = new FormData();
            formData.append("action", "delete_section");
            formData.append("section_id", id);

            fetch("../../conf/php/sections_data.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                deleteModal.classList.add("hidden");
                if (res.success) {
                    showAlert(res.message, "success");
                    loadSections(); // Refresh sections table
                } else {
                    showAlert(res.message, "error");
                }
            })
            .catch(err => {
                deleteModal.classList.add("hidden");
                console.error("Error deleting section:", err);
                showAlert("Server error deleting section", "error");
            });
        });
    }

    // 5. Search Section Filter
    const searchInput = document.getElementById("sectionSearchInput");
    const topSearch = document.getElementById("topSearchInput");

    function filterSections(query) {
        const rows = document.querySelectorAll("#sectionsTableBody tr.section-row");
        rows.forEach(r => {
            r.style.display = r.textContent.toLowerCase().includes(query) ? "" : "none";
        });
    }

    if (searchInput) {
        searchInput.addEventListener("input", (e) => {
            filterSections(e.target.value.toLowerCase().trim());
        });
    }

    if (topSearch) {
        topSearch.addEventListener("input", (e) => {
            filterSections(e.target.value.toLowerCase().trim());
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

    // 6. Add Section Modal & Form
    const addModal = document.getElementById("addSectionModal");
    const openAddBtn = document.getElementById("openAddSectionModal");
    const closeAddBtn = document.getElementById("closeAddSectionModal");
    const cancelAddBtn = document.getElementById("cancelAddSectionBtn");
    const addForm = document.getElementById("addSectionForm");

    if (openAddBtn) openAddBtn.addEventListener("click", () => addModal.classList.remove("hidden"));
    if (closeAddBtn) closeAddBtn.addEventListener("click", () => addModal.classList.add("hidden"));
    if (cancelAddBtn) cancelAddBtn.addEventListener("click", () => addModal.classList.add("hidden"));

    if (addForm) {
        addForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const formData = new FormData(addForm);

            fetch("../../conf/php/sections_data.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.conflict) {
                    showConflictModal(res.message);
                    return;
                }
                if (res.success) {
                    addModal.classList.add("hidden");
                    addForm.reset();
                    showAlert(res.message, "success");
                    loadSections(); // Refresh table dynamically!
                } else {
                    showAlert(res.message, "error");
                }
            })
            .catch(err => {
                console.error("Error creating section:", err);
                showAlert("Server error adding section", "error");
            });
        });
    }

    // Schedule Conflict Modal Helper
    const conflictModal = document.getElementById("scheduleConflictModal");
    const conflictMsgEl = document.getElementById("scheduleConflictMessage");
    const closeConflictBtn = document.getElementById("closeConflictModalBtn");

    function showConflictModal(message) {
        if (conflictMsgEl) {
            conflictMsgEl.textContent = message || "Schedule conflict: may kasabay nang section sa parehong araw at oras.";
        }
        if (conflictModal) {
            conflictModal.classList.remove("hidden");
        }
    }

    if (closeConflictBtn && conflictModal) {
        closeConflictBtn.addEventListener("click", () => {
            conflictModal.classList.add("hidden");
        });
    }

    // 7. CSV Modal
    const csvModal = document.getElementById("csvModal");
    const uploadBtn = document.getElementById("uploadCsvBtn");
    const closeCsv = document.getElementById("closeCsvModal");
    const cancelCsv = document.getElementById("cancelCsvBtn");

    if (uploadBtn) uploadBtn.addEventListener("click", () => csvModal.classList.remove("hidden"));
    if (closeCsv) closeCsv.addEventListener("click", () => csvModal.classList.add("hidden"));
    if (cancelCsv) cancelCsv.addEventListener("click", () => csvModal.classList.add("hidden"));

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

    function formatTime(t) {
        if (!t) return '--';
        const [hStr, mStr] = t.split(':');
        let h = parseInt(hStr, 10);
        const m = mStr || '00';
        const ampm = h >= 12 ? 'PM' : 'AM';
        if (h === 0) h = 12;
        else if (h > 12) h -= 12;
        return `${h}:${m} ${ampm}`;
    }

    // ---- Day Picker Logic ----
    function initDayPicker(pickerId, hiddenInputId, summaryId) {
        const group = document.getElementById(pickerId);
        const hiddenInput = document.getElementById(hiddenInputId);
        const summaryEl = document.getElementById(summaryId);
        if (!group || !hiddenInput) return;

        function updateSummary() {
            const selected = [...group.querySelectorAll('.day-chip.active')].map(b => b.dataset.day);
            hiddenInput.value = selected.join(', ');
            if (summaryEl) {
                summaryEl.innerHTML = selected.length
                    ? `Selected: <strong style="color:#2563eb">${selected.join(', ')}</strong>`
                    : '<span style="color:#94a3b8">No days selected</span>';
            }
        }

        group.querySelectorAll('.day-chip').forEach(chip => {
            chip.addEventListener('click', () => {
                chip.classList.toggle('active');
                updateSummary();
            });
        });

        updateSummary();
    }

    // Initialize day pickers for Add and Edit modals
    initDayPicker('addDayPicker', 'addScheduleDayInput', 'addScheduleSummary');
    initDayPicker('editDayPicker', 'editScheduleDay', 'editScheduleSummary');
});
