// Section Details Client Logic with Excel Masterlist Import
document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    const sectionId = urlParams.get("id");

    if (!sectionId) {
        window.location.href = "sections.html";
        return;
    }

    const hiddenInput = document.getElementById("hiddenSectionId");
    if (hiddenInput) hiddenInput.value = sectionId;

    let parsedStudentsToImport = [];

    loadSectionDetails();

    function loadSectionDetails() {
        fetch(`../../conf/php/section_details_data.php?id=${sectionId}`)
            .then(res => {
                if (res.status === 401) {
                    window.location.href = "login.html";
                    return null;
                }
                return res.json();
            })
            .then(data => {
                if (!data || !data.success) {
                    showAlert(data ? data.message : "Error loading section", "error");
                    setTimeout(() => window.location.href = "sections.html", 1500);
                    return;
                }
                renderDetails(data);
            })
            .catch(err => {
                console.error("Failed to load section details:", err);
            });
    }

    function renderDetails(data) {
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

        const sec = data.section;
        const students = data.students || [];

        const topDate = document.getElementById("topbarDate");
        if (topDate) topDate.textContent = data.today_date;

        const heading = document.getElementById("sectionNameHeading");
        if (heading) heading.textContent = sec.section_name;

        const meta = document.getElementById("sectionMetaInfo");
        if (meta) {
            meta.innerHTML = `
                <strong>${escapeHtml(sec.subject_code)}:</strong> ${escapeHtml(sec.subject_name)} &middot; 
                ${escapeHtml(sec.room)} &middot; 
                ${escapeHtml(sec.schedule_day)} (${formatTime(sec.start_time)} - ${formatTime(sec.end_time)})
            `;
        }

        const stuCount = document.getElementById("sectionStudentCount");
        if (stuCount) stuCount.textContent = data.total_students;

        const sessCount = document.getElementById("sectionSessionCount");
        if (sessCount) sessCount.textContent = data.total_sessions;

        const roomSched = document.getElementById("sectionRoomSchedule");
        if (roomSched) roomSched.textContent = sec.room;

        const dayTime = document.getElementById("sectionDayTime");
        if (dayTime) dayTime.textContent = `${sec.schedule_day} · ${formatTime(sec.start_time)}`;

        // Official Student Masterlist Table (Matching User Excel Image Format)
        const tbody = document.getElementById("studentsTableBody");
        if (tbody) {
            if (students.length > 0) {
                tbody.innerHTML = students.map((s, index) => {
                    const fullName = s.last_name !== 'N/A' 
                        ? `${s.last_name}, ${s.first_name}` 
                        : s.first_name;

                    const sexBadge = s.sex 
                        ? `<span style="font-weight: 700; font-size: 11px; padding: 3px 8px; border-radius: 6px; ${s.sex.toUpperCase() === 'M' ? 'background: #eff6ff; color: #1d4ed8;' : 'background: #fdf2f8; color: #be185d;'}">${escapeHtml(s.sex.toUpperCase())}</span>`
                        : `<span style="color: #94a3b8;">--</span>`;

                    return `
                        <tr class="student-row">
                            <td style="text-align: center; color: #64748b; font-weight: 600;">${index + 1}</td>
                            <td style="font-weight: 700; color: #0f172a; font-family: monospace; font-size: 13px;">
                                ${escapeHtml(s.student_number)}
                            </td>
                            <td style="font-weight: 700; color: #1e293b;">
                                ${escapeHtml(fullName.toUpperCase())}
                            </td>
                            <td style="font-weight: 600; color: #0284c7;">
                                ${escapeHtml(s.course || 'BSIT')}
                            </td>
                            <td style="color: #475569; font-size: 12.5px;">
                                ${escapeHtml(s.year_level || 'N/A')}
                            </td>
                            <td style="color: #64748b; font-size: 12.5px;">
                                ${escapeHtml(s.email || 'N/A')}
                            </td>
                            <td style="text-align: center;">
                                ${sexBadge}
                            </td>
                            <td style="text-align: center;">
                                <span class="students-badge" style="font-size: 11.5px; padding: 4px 10px;">
                                    ${s.total_sessions_attended} sessions
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="btn-delete-student" data-id="${s.id}" data-name="${escapeHtml(fullName)}" title="Remove student" style="background: transparent; border: none; color: #ef4444; cursor: pointer; padding: 4px 8px; border-radius: 6px;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join("");
            } else {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="empty-state-cell" style="padding: 36px 20px; text-align: center;">
                            <div style="color: #64748b; font-size: 14px; margin-bottom: 8px;">No students enrolled in this section yet.</div>
                            <div style="font-size: 12.5px; color: #94a3b8;">Click <strong>"Upload Student Masterlist"</strong> above to import your Excel file or <strong>"+ Add Student"</strong> to enter manually.</div>
                        </td>
                    </tr>
                `;
            }
        }
    }

    // 2. Filter / Search Student
    const filterInput = document.getElementById("filterStudentInput");
    if (filterInput) {
        filterInput.addEventListener("input", (e) => {
            const query = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll("#studentsTableBody tr.student-row");
            rows.forEach(r => {
                r.style.display = r.textContent.toLowerCase().includes(query) ? "" : "none";
            });
        });
    }

    // 3. Delete Student Handler
    document.addEventListener("click", (e) => {
        const delBtn = e.target.closest(".btn-delete-student");
        if (delBtn) {
            const stuId = delBtn.dataset.id;
            const stuName = delBtn.dataset.name;
            if (confirm(`Remove student "${stuName}" from this section masterlist?`)) {
                const formData = new FormData();
                formData.append("action", "delete_student");
                formData.append("id", sectionId);
                formData.append("student_id", stuId);

                fetch("../../conf/php/section_details_data.php", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showAlert(res.message, "success");
                        loadSectionDetails();
                    } else {
                        showAlert(res.message, "error");
                    }
                })
                .catch(err => {
                    console.error("Error removing student:", err);
                    showAlert("Failed to remove student", "error");
                });
            }
        }
    });

    // 4. Excel Masterlist Upload & Parse Logic
    const uploadModal = document.getElementById("uploadMasterlistModal");
    const openUploadBtn = document.getElementById("openUploadMasterlistModal");
    const closeUploadBtn = document.getElementById("closeUploadModal");
    const cancelUploadBtn = document.getElementById("cancelUploadBtn");
    const dropZone = document.getElementById("dropZone");
    const fileInput = document.getElementById("masterlistFileInput");
    const previewContainer = document.getElementById("filePreviewContainer");
    const previewFileName = document.getElementById("previewFileName");
    const previewStudentCount = document.getElementById("previewStudentCount");
    const previewSampleTable = document.getElementById("previewSampleTable");
    const confirmImportBtn = document.getElementById("confirmImportBtn");

    if (openUploadBtn) {
        openUploadBtn.addEventListener("click", () => {
            resetUploadState();
            uploadModal.classList.remove("hidden");
        });
    }

    if (closeUploadBtn) closeUploadBtn.addEventListener("click", () => uploadModal.classList.add("hidden"));
    if (cancelUploadBtn) cancelUploadBtn.addEventListener("click", () => uploadModal.classList.add("hidden"));

    if (dropZone && fileInput) {
        dropZone.addEventListener("click", () => fileInput.click());

        dropZone.addEventListener("dragover", (e) => {
            e.preventDefault();
            dropZone.style.borderColor = "#0284c7";
            dropZone.style.background = "#e0f2fe";
        });

        dropZone.addEventListener("dragleave", () => {
            dropZone.style.borderColor = "#0284c7";
            dropZone.style.background = "#f0f9ff";
        });

        dropZone.addEventListener("drop", (e) => {
            e.preventDefault();
            dropZone.style.borderColor = "#0284c7";
            dropZone.style.background = "#f0f9ff";
            if (e.dataTransfer.files.length > 0) {
                handleFileSelect(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener("change", (e) => {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });
    }

    function resetUploadState() {
        parsedStudentsToImport = [];
        if (fileInput) fileInput.value = "";
        if (previewContainer) previewContainer.classList.add("hidden");
        if (confirmImportBtn) {
            confirmImportBtn.disabled = true;
            confirmImportBtn.style.opacity = "0.5";
            confirmImportBtn.textContent = "Import Masterlist to Section";
        }
    }

    function handleFileSelect(file) {
        if (!file) return;

        const fileName = file.name || "masterlist.xls";
        const ext = fileName.includes('.') ? fileName.split('.').pop().toLowerCase() : '';

        if (ext && !['xlsx', 'xls', 'csv'].includes(ext)) {
            alert("Please select a valid Excel (.xlsx, .xls) or CSV file.");
            return;
        }

        const reader = new FileReader();

        reader.onload = function(e) {
            let workbook;
            try {
                if (typeof XLSX === "undefined") {
                    alert("Excel engine loading error. Please refresh the page.");
                    return;
                }

                const data = new Uint8Array(e.target.result);
                workbook = XLSX.read(data, { type: 'array' });
            } catch (readErr) {
                console.warn("XLSX read with arrayBuffer failed, attempting binary string fallback:", readErr);
                try {
                    let binary = "";
                    const bytes = new Uint8Array(e.target.result);
                    const len = bytes.byteLength;
                    for (let i = 0; i < len; i++) {
                        binary += String.fromCharCode(bytes[i]);
                    }
                    workbook = XLSX.read(binary, { type: 'binary' });
                } catch (fallbackErr) {
                    console.error("Fatal Excel read error:", fallbackErr);
                    alert("Failed to read Excel file. Please ensure it is a valid spreadsheet (.xlsx, .xls, or .csv).");
                    return;
                }
            }

            try {
                const firstSheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[firstSheetName];
                const rawRows = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

                parseExcelRows(rawRows, fileName);
            } catch (parseErr) {
                console.error("Error processing Excel content:", parseErr);
                alert("Error processing Excel content: " + parseErr.message);
            }
        };

        reader.readAsArrayBuffer(file);
    }

    function cleanCell(val) {
        if (val === null || val === undefined) return "";
        return String(val).replace(/\s+/g, " ").trim();
    }

    function normalizeHeader(val) {
        return cleanCell(val).toLowerCase()
            .replace(/[\._#:\-–—\(\)\/]/g, " ")
            .replace(/\s+/g, " ")
            .trim();
    }

    function parseExcelRows(rows, fileName) {
        if (!rows || rows.length < 2) {
            alert("The Excel file does not contain enough data rows.");
            return;
        }

        const studentIdKeywords = [
            "student no", "student_no", "student no.", "student number", "student numbers",
            "student id", "student_id", "student id no", "student id no.", "student id number",
            "id number", "id no", "id no.", "id_no", "id num", "stud no", "stud_no", "stud id",
            "stud id no", "stud number", "student code", "student num", "lrn", "sn", "id"
        ];

        const fullNameKeywords = [
            "student name", "student's name", "students name", "name of student", "names of students",
            "full name", "fullname", "complete name", "student full name", "name", "pangalan"
        ];

        const lastNameKeywords = [
            "last name", "lastname", "last_name", "surname", "family name", "apelyido"
        ];

        const firstNameKeywords = [
            "first name", "firstname", "first_name", "given name", "first", "pangalan"
        ];

        const middleNameKeywords = [
            "middle name", "middlename", "middle_name", "middle initial", "m.i.", "mi", "m i", "middle"
        ];

        const courseKeywords = [
            "course", "program", "strand", "degree", "curriculum", "dept", "department",
            "academic program", "course program", "program course"
        ];

        const yearKeywords = [
            "year level", "year_level", "year", "yr level", "yr_level", "level", "grade",
            "yr", "year sec", "year section", "standing", "yr lvl"
        ];

        const sexKeywords = [
            "sex", "gender", "kasarian"
        ];

        const emailKeywords = [
            "email", "e mail", "email address", "student email", "deped email", "school email", "institutional email"
        ];

        let headerRowIndex = -1;
        let colMap = {
            student_no: -1,
            name: -1,
            last_name: -1,
            first_name: -1,
            middle_name: -1,
            course: -1,
            year_level: -1,
            email: -1,
            sex: -1
        };

        // 1. Keyword-based Header Detection (scanning first 35 rows)
        for (let r = 0; r < Math.min(rows.length, 35); r++) {
            const rawRow = rows[r];
            if (!Array.isArray(rawRow) || rawRow.length === 0) continue;

            const headers = Array.from({ length: rawRow.length }, (_, i) => normalizeHeader(rawRow[i]));

            let sIdx = headers.findIndex(h => h && studentIdKeywords.some(k => h === k || h.startsWith(k + " ") || h.endsWith(" " + k)));
            let nIdx = headers.findIndex(h => h && fullNameKeywords.some(k => h === k || h.startsWith(k + " ") || h.endsWith(" " + k)));
            let lnIdx = headers.findIndex(h => h && lastNameKeywords.some(k => h === k || h.startsWith(k + " ") || h.endsWith(" " + k)));
            let fnIdx = headers.findIndex(h => h && firstNameKeywords.some(k => h === k || h.startsWith(k + " ") || h.endsWith(" " + k)));

            if (sIdx !== -1 && (nIdx !== -1 || (lnIdx !== -1 && fnIdx !== -1))) {
                headerRowIndex = r;
                colMap.student_no = sIdx;
                colMap.name = nIdx;
                colMap.last_name = lnIdx;
                colMap.first_name = fnIdx;
                colMap.middle_name = headers.findIndex(h => h && middleNameKeywords.some(k => h === k || h.startsWith(k + " ") || h.endsWith(" " + k)));
                colMap.course = headers.findIndex(h => h && courseKeywords.some(k => h === k || h.startsWith(k + " ") || h.endsWith(" " + k)));
                colMap.year_level = headers.findIndex(h => h && yearKeywords.some(k => h === k || h.startsWith(k + " ") || h.endsWith(" " + k)));
                colMap.email = headers.findIndex(h => h && emailKeywords.some(k => h === k || h.startsWith(k + " ") || h.endsWith(" " + k)));
                colMap.sex = headers.findIndex(h => h && sexKeywords.some(k => h === k || h.startsWith(k + " ") || h.endsWith(" " + k)));
                break;
            }
        }

        // 2. Fallback: Content-Inspection Heuristic if headers weren't identified
        if (headerRowIndex === -1) {
            console.log("Header keywords not strictly matched. Running content inspection heuristic...");
            const maxCols = Math.max(...rows.slice(0, 30).map(r => Array.isArray(r) ? r.length : 0));
            let bestStudentCol = -1, bestStudentScore = 0;
            let bestNameCol = -1, bestNameScore = 0;
            let bestSexCol = -1, bestSexScore = 0;

            for (let c = 0; c < maxCols; c++) {
                let idMatches = 0;
                let nameMatches = 0;
                let sexMatches = 0;

                for (let r = 0; r < Math.min(rows.length, 30); r++) {
                    const cellVal = cleanCell(rows[r]?.[c]);
                    if (!cellVal) continue;

                    // Check for student number pattern (contains digits, 4-20 chars)
                    if (/^(?:[A-Za-z0-9]{1,6}[-\s]?)?[0-9]{3,12}(?:[-–][0-9]{1,6})?$/.test(cellVal) && /\d/.test(cellVal)) {
                        idMatches++;
                    }
                    // Check for human name pattern (has alpha letters, possible comma, not purely numeric)
                    if (/^[A-Za-zñÑ\s\.,'-]{3,50}$/.test(cellVal) && !/\d/.test(cellVal) && cellVal.length > 3) {
                        nameMatches++;
                    }
                    // Check for sex pattern
                    if (/^(?:M|F|MALE|FEMALE)$/i.test(cellVal)) {
                        sexMatches++;
                    }
                }

                if (idMatches > bestStudentScore) {
                    bestStudentScore = idMatches;
                    bestStudentCol = c;
                }
                if (nameMatches > bestNameScore && c !== bestStudentCol) {
                    bestNameScore = nameMatches;
                    bestNameCol = c;
                }
                if (sexMatches > bestSexScore) {
                    bestSexScore = sexMatches;
                    bestSexCol = c;
                }
            }

            if (bestStudentCol !== -1 && bestNameCol !== -1) {
                headerRowIndex = 0;
                colMap.student_no = bestStudentCol;
                colMap.name = bestNameCol;
                colMap.sex = bestSexCol;
            } else {
                headerRowIndex = 0;
                colMap = {
                    student_no: 1,
                    name: 2,
                    course: 3,
                    year_level: 4,
                    email: 8,
                    sex: 9
                };
            }
        }

        // 3. Extract and sanitize student records
        const ignoredKeywords = [
            "total", "count", "male", "female", "prepared by", "noted by", "submitted by",
            "checked by", "approved by", "signature", "instructor", "adviser", "dean",
            "faculty", "page", "legend", "status", "passed", "failed", "summary", "no."
        ];

        parsedStudentsToImport = [];

        for (let r = headerRowIndex + 1; r < rows.length; r++) {
            const row = rows[r];
            if (!Array.isArray(row) || row.length === 0) continue;

            let studentNo = cleanCell(row[colMap.student_no]);
            if (/^\d+\.0$/.test(studentNo)) {
                studentNo = studentNo.replace(/\.0$/, "");
            }

            const sLower = studentNo.toLowerCase();
            if (!studentNo || ignoredKeywords.some(k => sLower === k || sLower.startsWith(k + ":") || sLower.startsWith(k + " "))) {
                continue;
            }
            if (studentIdKeywords.includes(normalizeHeader(studentNo))) {
                continue;
            }

            // Extract Name (split or single column)
            let fullName = "";
            let firstName = "";
            let lastName = "";

            if (colMap.last_name !== -1 && colMap.first_name !== -1) {
                lastName = cleanCell(row[colMap.last_name]);
                firstName = cleanCell(row[colMap.first_name]);
                const middleName = colMap.middle_name !== -1 ? cleanCell(row[colMap.middle_name]) : "";

                if (lastName && firstName) {
                    fullName = `${lastName}, ${firstName}${middleName ? " " + middleName : ""}`;
                } else {
                    fullName = lastName || firstName || "";
                }
            } else if (colMap.name !== -1) {
                fullName = cleanCell(row[colMap.name]);
                if (fullName.includes(",")) {
                    const p = fullName.split(",");
                    lastName = p[0].trim();
                    firstName = p.slice(1).join(",").trim();
                } else {
                    const w = fullName.split(/\s+/);
                    lastName = w.length > 1 ? w[w.length - 1] : "N/A";
                    firstName = w.length > 1 ? w.slice(0, -1).join(" ") : fullName;
                }
            }

            const nLower = fullName.toLowerCase();
            if (!fullName || ignoredKeywords.some(k => nLower === k || nLower.startsWith(k + ":") || nLower.startsWith(k + " "))) {
                continue;
            }
            if (fullNameKeywords.includes(normalizeHeader(fullName))) {
                continue;
            }

            const courseVal = colMap.course !== -1 ? cleanCell(row[colMap.course]) : "";
            const yearVal = colMap.year_level !== -1 ? cleanCell(row[colMap.year_level]) : "";
            const emailVal = colMap.email !== -1 ? cleanCell(row[colMap.email]) : "";
            let sexVal = colMap.sex !== -1 ? cleanCell(row[colMap.sex]).toUpperCase() : "";

            if (sexVal === "MALE" || sexVal.startsWith("M")) sexVal = "M";
            else if (sexVal === "FEMALE" || sexVal.startsWith("F")) sexVal = "F";

            parsedStudentsToImport.push({
                student_number: studentNo,
                name: fullName,
                first_name: firstName,
                last_name: lastName,
                course: courseVal,
                year_level: yearVal,
                email: emailVal,
                sex: sexVal
            });
        }

        if (parsedStudentsToImport.length === 0) {
            alert("No valid student records found in the uploaded file. Please make sure the spreadsheet contains student IDs and names.");
            resetUploadState();
            return;
        }

        // 4. Render Preview in Modal
        if (previewFileName) previewFileName.textContent = fileName;
        if (previewStudentCount) previewStudentCount.textContent = `${parsedStudentsToImport.length} students detected`;

        const sampleRows = parsedStudentsToImport.slice(0, 5);
        if (previewSampleTable) {
            previewSampleTable.innerHTML = `
                <table style="width: 100%; border-collapse: collapse; font-size: 11.5px;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569;">
                            <th style="padding: 6px; text-align: left;">Student No</th>
                            <th style="padding: 6px; text-align: left;">Name</th>
                            <th style="padding: 6px; text-align: left;">Course</th>
                            <th style="padding: 6px; text-align: left;">Email</th>
                            <th style="padding: 6px; text-align: center;">Sex</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${sampleRows.map(s => `
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 6px; font-weight: 700; color: #0f172a; font-family: monospace;">${escapeHtml(s.student_number)}</td>
                                <td style="padding: 6px; color: #1e293b; font-weight: 600;">${escapeHtml(s.name)}</td>
                                <td style="padding: 6px; color: #0284c7;">${escapeHtml(s.course || 'BSIT')}</td>
                                <td style="padding: 6px; color: #64748b;">${escapeHtml(s.email || '--')}</td>
                                <td style="padding: 6px; text-align: center; font-weight: 700; color: ${s.sex === 'F' ? '#be185d' : '#1d4ed8'};">${escapeHtml(s.sex || '--')}</td>
                            </tr>
                        `).join("")}
                    </tbody>
                </table>
                ${parsedStudentsToImport.length > 5 ? `<div style="text-align: center; color: #94a3b8; padding: 6px; font-size: 11px;">...and ${parsedStudentsToImport.length - 5} more students ready to import</div>` : ''}
            `;
        }

        if (previewContainer) previewContainer.classList.remove("hidden");
        if (confirmImportBtn) {
            confirmImportBtn.disabled = false;
            confirmImportBtn.style.opacity = "1";
            confirmImportBtn.textContent = `Import ${parsedStudentsToImport.length} Students to Masterlist`;
        }
    }

    // 5. Submit Imported Students to Backend API
    if (confirmImportBtn) {
        confirmImportBtn.addEventListener("click", () => {
            if (parsedStudentsToImport.length === 0) return;

            confirmImportBtn.disabled = true;
            confirmImportBtn.textContent = "Importing into database...";

            const formData = new FormData();
            formData.append("action", "import_masterlist");
            formData.append("id", sectionId);
            formData.append("students", JSON.stringify(parsedStudentsToImport));

            fetch("../../conf/php/section_details_data.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                uploadModal.classList.add("hidden");
                resetUploadState();

                if (res.success) {
                    showAlert(res.message, "success");
                    loadSectionDetails(); // Refresh live masterlist table!
                } else {
                    showAlert(res.message, "error");
                }
            })
            .catch(err => {
                confirmImportBtn.disabled = false;
                confirmImportBtn.textContent = "Import Masterlist to Section";
                console.error("Error importing masterlist:", err);
                showAlert("Failed to import student masterlist", "error");
            });
        });
    }

    // 6. Manual Add Student Form
    const addModal = document.getElementById("addStudentModal");
    const openAddBtn = document.getElementById("openAddStudentModal");
    const closeAddBtn = document.getElementById("closeAddStudentModal");
    const cancelAddBtn = document.getElementById("cancelAddStudentBtn");
    const addForm = document.getElementById("addStudentForm");

    if (openAddBtn) openAddBtn.addEventListener("click", () => addModal.classList.remove("hidden"));
    if (closeAddBtn) closeAddBtn.addEventListener("click", () => addModal.classList.add("hidden"));
    if (cancelAddBtn) cancelAddBtn.addEventListener("click", () => addModal.classList.add("hidden"));

    if (addForm) {
        addForm.addEventListener("submit", (e) => {
            e.preventDefault();
            const formData = new FormData(addForm);

            fetch("../../conf/php/section_details_data.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    addModal.classList.add("hidden");
                    addForm.reset();
                    showAlert(res.message, "success");
                    loadSectionDetails();
                } else {
                    showAlert(res.message, "error");
                }
            })
            .catch(err => {
                console.error("Error adding student:", err);
                showAlert("Server error enrolling student", "error");
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

    // Student Masterlist Search Filter
    const filterStudentInput = document.getElementById("filterStudentInput");
    if (filterStudentInput) {
        filterStudentInput.addEventListener("input", (e) => {
            const query = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll("#studentsTable tbody tr");
            rows.forEach(r => {
                if (r.classList.contains("empty-state-cell") || r.cells.length < 3) return;
                r.style.display = r.textContent.toLowerCase().includes(query) ? "" : "none";
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

    function escapeHtml(str) {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function formatTime(t) {
        if (!t) return "--:--";
        const parts = t.split(":");
        let h = parseInt(parts[0], 10);
        const m = parts[1] || "00";
        const ampm = h >= 12 ? "PM" : "AM";
        h = h % 12 || 12;
        return `${h}:${m} ${ampm}`;
    }
});
