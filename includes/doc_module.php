<?php
/**
 * includes/doc_module.php
 * Unified UI and Logic for e-Document System
 */

/**
 * Render the document list table with filters
 * @param string $doc_type 'incoming'|'outgoing'|'order'|'memo'
 * @param array $config Configuration for the table
 */
function renderDocumentList($doc_type, $config = []) {
    $title = $config['title'] ?? 'รายการเอกสาร';
    $table = $config['table'] ?? '';
    ?>
    <div class="card border-0 shadow-sm rounded-2xl overflow-hidden">
        <div class="card-header bg-white py-4 border-0">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                <h5 class="mb-0 font-black text-slate-800">
                    <i class="bi bi-file-earmark-text text-blue-600 me-2"></i><?= $title ?>
                </h5>
                <div class="d-flex gap-2">
                    <div class="input-group input-group-sm" style="max-width: 250px;">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="docSearch" class="form-control bg-light border-0" placeholder="ค้นหาเอกสาร...">
                    </div>
                    <select id="filterYear" class="form-select form-select-sm bg-light border-0" style="width: 120px;">
                        <option value="">ทุกปี พ.ศ.</option>
                        <?php 
                        $currentYear = date('Y') + 543;
                        for($y = $currentYear; $y >= $currentYear - 10; $y--) {
                            echo "<option value='$y'>$y</option>";
                        }
                        ?>
                    </select>
                    <button class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm" onclick="openAddModal()">
                        <i class="bi bi-plus-lg me-1"></i> เพิ่มเอกสาร
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="docTable">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-widest border-0">เลขที่ / วันที่</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-widest border-0">เรื่อง</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-widest border-0 text-center">ไฟล์แนบ</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-widest border-0 text-center">สถานะ</th>
                            <th class="px-4 py-3 text-xs font-black text-slate-400 uppercase tracking-widest border-0 text-end">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody id="docListBody">
                        <!-- Content loaded via AJAX -->
                        <tr>
                            <td colspan="5" class="text-center py-10">
                                <div class="spinner-border text-primary spinner-border-sm" role="status"></div>
                                <span class="ms-2 text-slate-400">กำลังโหลดข้อมูล...</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 bg-white border-top border-slate-50 d-flex justify-content-between align-items-center">
                <div class="text-xs font-bold text-slate-400">
                    แสดง <span id="startRange">0</span> - <span id="endRange">0</span> จาก <span id="totalCount">0</span> รายการ
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Script to load data -->
    <script>
    const docType = '<?= $doc_type ?>';
    const docTable = '<?= $table ?>';
    let currentPage = 1;

    $(document).ready(function() {
        loadDocs();

        $('#docSearch, #filterYear').on('change keyup', function() {
            currentPage = 1;
            loadDocs();
        });
    });

    function loadDocs() {
        const search = $('#docSearch').val();
        const year = $('#filterYear').val();
        
        $.ajax({
            url: '../ajax/documents.php',
            type: 'GET',
            data: {
                action: 'list',
                type: docType,
                table: docTable,
                search: search,
                year: year,
                page: currentPage
            },
            success: function(res) {
                if (res.success) {
                    renderTable(res.data);
                    renderPagination(res.pagination);
                } else {
                    $('#docListBody').html(`<tr><td colspan="5" class="text-center py-10 text-rose-500 font-bold"><i class="bi bi-exclamation-triangle me-2"></i>Error: ${res.message}</td></tr>`);
                }
            },
            error: function(xhr) {
                $('#docListBody').html(`<tr><td colspan="5" class="text-center py-10 text-rose-500 font-bold"><i class="bi bi-exclamation-triangle me-2"></i>ไม่สามารถโหลดข้อมูลได้ (HTTP ${xhr.status})</td></tr>`);
            }
        });
    }

    function renderTable(data) {
        const body = $('#docListBody');
        body.empty();
        
        if (data.length === 0) {
            body.append('<tr><td colspan="5" class="text-center py-10 text-slate-400">ไม่พบข้อมูลเอกสาร</td></tr>');
            return;
        }

        data.forEach(item => {
            const statusBadge = getStatusBadge(item.status);
            const attachmentsCount = item.attachments_count || 0;
            
            body.append(`
                <tr class="transition-all hover:bg-slate-50/50">
                    <td class="px-4 py-3">
                        <div class="font-bold text-slate-800">${item.doc_number}</div>
                        <div class="text-xs text-slate-400">${item.doc_date_formatted}</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-slate-700 text-truncate" style="max-width: 400px;">${item.subject}</div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button class="btn btn-link btn-sm text-decoration-none p-0" onclick="viewAttachments(${item.id})">
                            <span class="badge rounded-pill bg-blue-50 text-blue-600 px-3 py-2">
                                <i class="bi bi-paperclip me-1"></i>${attachmentsCount}
                            </span>
                        </button>
                    </td>
                    <td class="px-4 py-3 text-center">${statusBadge}</td>
                    <td class="px-4 py-3 text-end">
                        <div class="d-flex justify-content-end gap-1">
                            <button class="btn btn-light btn-sm rounded-pill text-blue-600" onclick="viewDoc(${item.id})" title="ดูรายละเอียด">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn btn-light btn-sm rounded-pill text-amber-600" onclick="editDoc(${item.id})" title="แก้ไข">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-light btn-sm rounded-pill text-rose-600" onclick="deleteDoc(${item.id})" title="ลบ">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function getStatusBadge(status) {
        switch(status) {
            case 'ประกาศใช้แล้ว': return '<span class="badge rounded-pill bg-emerald-50 text-emerald-600 px-3 py-2 font-bold"><i class="bi bi-check-circle me-1"></i>ประกาศใช้แล้ว</span>';
            case 'รออนุมัติ': return '<span class="badge rounded-pill bg-amber-50 text-amber-600 px-3 py-2 font-bold"><i class="bi bi-hourglass-split me-1"></i>รออนุมัติ</span>';
            case 'ยกเลิก': return '<span class="badge rounded-pill bg-rose-50 text-rose-600 px-3 py-2 font-bold"><i class="bi bi-x-circle me-1"></i>ยกเลิก</span>';
            default: return '<span class="badge rounded-pill bg-slate-50 text-slate-600 px-3 py-2 font-bold">' + status + '</span>';
        }
    }

    function renderPagination(p) {
        const wrap = $('#pagination');
        wrap.empty();
        
        $('#startRange').text(p.start);
        $('#endRange').text(p.end);
        $('#totalCount').text(p.total);

        if (p.total_pages <= 1) return;

        // Prev
        wrap.append(`<li class="page-item ${p.current_page == 1 ? 'disabled' : ''}"><a class="page-link border-0 rounded-pill mx-1" href="javascript:changePage(${p.current_page - 1})"><i class="bi bi-chevron-left"></i></a></li>`);
        
        // Pages (showing 5 pages max)
        for(let i = 1; i <= p.total_pages; i++) {
            if (i == 1 || i == p.total_pages || (i >= p.current_page - 2 && i <= p.current_page + 2)) {
                wrap.append(`<li class="page-item ${p.current_page == i ? 'active' : ''}"><a class="page-link border-0 rounded-pill mx-1" href="javascript:changePage(${i})">${i}</a></li>`);
            } else if (i == p.current_page - 3 || i == p.current_page + 3) {
                wrap.append(`<li class="page-item disabled"><span class="page-link border-0">...</span></li>`);
            }
        }

        // Next
        wrap.append(`<li class="page-item ${p.current_page == p.total_pages ? 'disabled' : ''}"><a class="page-link border-0 rounded-pill mx-1" href="javascript:changePage(${p.current_page + 1})"><i class="bi bi-chevron-right"></i></a></li>`);
    }

    function changePage(page) {
        currentPage = page;
        loadDocs();
    }
    </script>
    <?php
}

/**
 * Render the unified modal for adding/editing documents
 */
function renderDocumentModal($doc_type, $config = []) {
    ?>
    <!-- Document Modal -->
    <div class="modal fade" id="docModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-2xl rounded-[32px] overflow-hidden">
                <form id="docForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="type" value="<?= $doc_type ?>">
                    <input type="hidden" name="id" id="docId" value="">
                    
                    <div class="modal-header border-0 bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white">
                        <div class="d-flex align-items-center gap-3">
                            <div class="w-12 h-12 bg-white/20 rounded-2xl d-flex align-items-center justify-center text-xl">
                                <i class="bi bi-file-earmark-plus"></i>
                            </div>
                            <div>
                                <h5 class="modal-title font-black mb-0" id="modalTitle">เพิ่มเอกสารใหม่</h5>
                                <p class="mb-0 text-blue-100 text-xs font-bold uppercase tracking-widest">ข้อมูลพื้นฐานและไฟล์แนบ</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body p-6">
                        <div class="row g-4">
                            <!-- Left Column: Basic Info -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2 d-block">เลขที่เอกสาร</label>
                                    <input type="text" name="doc_number" id="doc_number" class="form-control bg-light border-0 rounded-2xl px-4 py-3" required placeholder="เช่น ศธ 04001/...">
                                </div>
                                <div class="mb-3">
                                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2 d-block">วันที่เอกสาร</label>
                                    <input type="date" name="doc_date" id="doc_date" class="form-control bg-light border-0 rounded-2xl px-4 py-3" required value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2 d-block">ปี พ.ศ.</label>
                                    <input type="number" name="year_be" id="year_be" class="form-control bg-light border-0 rounded-2xl px-4 py-3" required value="<?= date('Y') + 543 ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2 d-block">สถานะเอกสาร</label>
                                    <select name="status" id="status" class="form-select bg-light border-0 rounded-2xl px-4 py-3">
                                        <option value="รออนุมัติ">รออนุมัติ</option>
                                        <option value="ประกาศใช้แล้ว">ประกาศใช้แล้ว</option>
                                        <option value="ยกเลิก">ยกเลิก</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Right Column: Subject & Attachments -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2 d-block">เรื่อง</label>
                                    <textarea name="subject" id="subject" class="form-control bg-light border-0 rounded-2xl px-4 py-3" rows="3" required placeholder="สรุปหัวข้อเอกสาร..."></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2 d-block">ไฟล์แนบ (PDF, Word, Excel, รูปภาพ)</label>
                                    <div id="dropzone" class="border-2 border-dashed border-slate-200 rounded-2xl p-4 text-center cursor-pointer hover:border-blue-400 hover:bg-blue-50/50 transition-all">
                                        <i class="bi bi-cloud-arrow-up text-3xl text-slate-300"></i>
                                        <p class="mb-0 text-xs font-bold text-slate-400 mt-2">คลิกเพื่อเลือก หรือลากไฟล์มาวาง</p>
                                        <input type="file" name="attachments[]" id="fileInput" class="d-none" multiple>
                                    </div>
                                    <div id="fileList" class="mt-3 space-y-2">
                                        <!-- Selected files will list here -->
                                    </div>
                                <div class="mb-3">
                                    <label class="text-xs font-black text-slate-400 uppercase tracking-widest mb-2 d-block">มอบหมายให้บุคลากร</label>
                                    <select name="involved_users[]" id="involved_users" class="form-select select2-multiple" multiple="multiple" style="width: 100%">
                                        <!-- Loaded via AJAX -->
                                    </select>
                                    <div class="mt-2">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="sendTelegram" name="send_telegram" value="1">
                                            <label class="form-check-label text-xs font-bold text-slate-500" for="sendTelegram">ส่งแจ้งเตือนเข้า Telegram</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer border-0 p-6 pt-0">
                        <button type="button" class="btn btn-light rounded-2xl px-5 py-3 font-bold" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-primary rounded-2xl px-6 py-3 font-black shadow-lg shadow-blue-200">
                            <i class="bi bi-save me-2"></i>บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content border-0">
                <div class="modal-header bg-slate-900 text-white border-0 py-3">
                    <h5 class="modal-title font-black text-base" id="previewTitle">แสดงตัวอย่างเอกสาร</h5>
                    <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0 bg-slate-100 overflow-hidden">
                    <iframe id="previewIframe" src="" frameborder="0" class="w-100 h-100"></iframe>
                </div>
            </div>
        </div>
    </div>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
    const fileInput = document.getElementById('fileInput');
    const dropzone = document.getElementById('dropzone');
    const fileList = document.getElementById('fileList');
    let selectedFiles = [];

    // Drag & Drop
    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.classList.add('border-blue-400', 'bg-blue-50/50'); });
    dropzone.addEventListener('dragleave', () => { dropzone.classList.remove('border-blue-400', 'bg-blue-50/50'); });
    dropzone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropzone.classList.remove('border-blue-400', 'bg-blue-50/50');
        handleFiles(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', () => handleFiles(fileInput.files));

    function handleFiles(files) {
        for(let file of files) {
            selectedFiles.push(file);
            renderFileItem(file);
        }
    }

    function renderFileItem(file, isExisting = false) {
        const id = Math.random().toString(36).substr(2, 9);
        const item = document.createElement('div');
        item.className = 'd-flex align-items-center justify-content-between p-3 bg-light rounded-2xl border border-slate-100 file-item';
        item.dataset.id = id;
        
        const ext = file.name.split('.').pop().toLowerCase();
        const icon = getFileIcon(ext);
        
        item.innerHTML = `
            <div class="d-flex align-items-center gap-3 overflow-hidden">
                <div class="w-8 h-8 rounded-lg ${icon.bg} ${icon.color} d-flex align-items-center justify-center flex-shrink-0">
                    <i class="bi ${icon.bi}"></i>
                </div>
                <div class="text-truncate">
                    <div class="text-xs font-bold text-slate-700 text-truncate">${file.name}</div>
                    <div class="text-[10px] text-slate-400 uppercase">${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                </div>
            </div>
            <div class="d-flex gap-1">
                ${isExisting ? `<button type="button" class="btn btn-sm btn-light text-blue-600 rounded-lg" onclick="previewFile('${file.path}')"><i class="bi bi-eye"></i></button>` : ''}
                <button type="button" class="btn btn-sm btn-light text-rose-600 rounded-lg" onclick="removeFile('${id}', ${isExisting ? file.id : 'null'})"><i class="bi bi-trash"></i></button>
            </div>
        `;
        fileList.appendChild(item);
    }

    function getFileIcon(ext) {
        switch(ext) {
            case 'pdf': return { bi: 'bi-file-earmark-pdf', bg: 'bg-rose-100', color: 'text-rose-600' };
            case 'doc': case 'docx': return { bi: 'bi-file-earmark-word', bg: 'bg-blue-100', color: 'text-blue-600' };
            case 'xls': case 'xlsx': return { bi: 'bi-file-earmark-excel', bg: 'bg-emerald-100', color: 'text-emerald-600' };
            case 'ppt': case 'pptx': return { bi: 'bi-file-earmark-slides', bg: 'bg-orange-100', color: 'text-orange-600' };
            case 'jpg': case 'jpeg': case 'png': return { bi: 'bi-file-earmark-image', bg: 'bg-purple-100', color: 'text-purple-600' };
            default: return { bi: 'bi-file-earmark', bg: 'bg-slate-100', color: 'text-slate-600' };
        }
    }

    function openAddModal() {
        document.getElementById('docForm').reset();
        document.getElementById('docId').value = '';
        document.getElementById('modalTitle').textContent = 'เพิ่มเอกสารใหม่';
        fileList.innerHTML = '';
        selectedFiles = [];
        
        // Reset Select2
        $('#involved_users').val(null).trigger('change');
        
        loadUsers();
        $('#docModal').modal('show');
    }

    function loadUsers() {
        if ($('#involved_users').children().length > 0) return;
        $.get('../ajax/documents.php', { action: 'get_users' }, function(res) {
            if (res.success) {
                res.data.forEach(user => {
                    $('#involved_users').append(new Option(user.text, user.id, false, false));
                });
                $('#involved_users').select2({
                    dropdownParent: $('#docModal'),
                    placeholder: 'เลือกบุคลากร...',
                    allowClear: true
                });
            }
        });
    }

    function previewFile(path) {
        const url = window.location.origin + '/' + path;
        const viewerUrl = `https://docs.google.com/viewer?url=${encodeURIComponent(url)}&embedded=true`;
        document.getElementById('previewIframe').src = viewerUrl;
        $('#previewModal').modal('show');
    }

    $('#docForm').on('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        // Add new files from selectedFiles array
        selectedFiles.forEach((file, index) => {
            formData.append(`new_attachments[${index}]`, file);
        });

        $.ajax({
            url: '../ajax/documents.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                if (res.success) {
                    // Handle assignment if needed (though it's partly handled by handleSave now if I update handleSave)
                    // But I'll handle it via a separate call or update handleSave to handle involved_users
                    const docId = res.id;
                    const involvedUsers = $('#involved_users').val();
                    const sendTele = $('#sendTelegram').is(':checked');

                    if (involvedUsers && involvedUsers.length > 0) {
                        $.post('../ajax/documents.php', {
                            action: 'assign',
                            ref_id: docId,
                            ref_table: docTable,
                            user_ids: involvedUsers
                        }, function() {
                            if (sendTele) {
                                $.post('../ajax/documents.php', {
                                    action: 'send_telegram',
                                    ref_id: docId,
                                    ref_table: docTable,
                                    message: 'มอบหมายเอกสารใหม่: '
                                });
                            }
                        });
                    }

                    Swal.fire({ icon: 'success', title: 'สำเร็จ', text: res.message, timer: 2000, showConfirmButton: false });
                    $('#docModal').modal('hide');
                    loadDocs();
                } else {
                    Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: res.message });
                }
            }
        });
    });

    // Sortable for file list
    new Sortable(fileList, {
        animation: 150,
        ghostClass: 'bg-blue-50'
    });
    </script>
    <?php
}
