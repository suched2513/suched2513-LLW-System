<?php
/**
 * edocument/incoming.php
 * หนังสือรับ (Incoming Documents)
 */
session_start();
require_once __DIR__ . '/../config.php';

// Auth Guard
if (!isset($_SESSION['llw_role'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../includes/doc_module.php';

$pageTitle = 'หนังสือรับ (Incoming)';
$activeSystem = 'edoc'; // We'll add this to breadcrumbs/sidebar logic if needed

require_once __DIR__ . '/../components/layout_start.php';
?>

<!-- Include jQuery for AJAX (if not already in layout) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<div class="row">
    <div class="col-12">
        <?php 
        renderDocumentList('incoming', [
            'title' => 'รายการหนังสือรับ',
            'table' => 'edoc_incoming_documents'
        ]); 
        ?>
    </div>
</div>

<?php 
renderDocumentModal('incoming'); 
require_once __DIR__ . '/../components/layout_end.php'; 
?>

<script>
// Custom logic for this page if any
function editDoc(id) {
    $.ajax({
        url: '/ajax/documents.php',
        type: 'GET',
        data: { action: 'get', type: 'incoming', id: id },
        success: function(res) {
            if (res.success) {
                const data = res.data;
                $('#docId').val(data.id);
                $('#doc_number').val(data.doc_number);
                $('#doc_date').val(data.doc_date);
                $('#subject').val(data.subject);
                $('#status').val(data.status);
                $('#year_be').val(data.year_be);
                
                $('#modalTitle').textContent = 'แก้ไขหนังสือรับ';
                
                // Render existing attachments
                fileList.innerHTML = '';
                if (data.attachments) {
                    data.attachments.forEach(att => {
                        renderFileItem({ id: att.id, name: att.file_name, size: att.file_size, path: att.file_path }, true);
                    });
                }
                
                // Load involved users
                loadUsers();
                $.get('/ajax/documents.php', { action: 'get_involved_users', ref_id: id, ref_table: 'edoc_incoming_documents' }, function(invRes) {
                    if (invRes.success) {
                        const invUserIds = invRes.data.map(u => u.user_id);
                        $('#involved_users').val(invUserIds).trigger('change');
                    }
                });
                
                $('#docModal').modal('show');
            }
        }
    });
}

function deleteDoc(id) {
    Swal.fire({
        title: 'ยืนยันการลบ?',
        text: "คุณต้องการลบเอกสารนี้ใช่หรือไม่? การดำเนินการนี้ไม่สามารถย้อนกลับได้",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ลบข้อมูล',
        cancelButtonText: 'ยกเลิก',
        customClass: { popup: 'rounded-[2rem]' }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '/ajax/documents.php',
                type: 'POST',
                data: { action: 'delete', type: 'incoming', id: id },
                success: function(res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'สำเร็จ', text: res.message, timer: 1500, showConfirmButton: false });
                        loadDocs();
                    } else {
                        Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: res.message });
                    }
                }
            });
        }
    });
}

function removeFile(uiId, dbId) {
    if (dbId) {
        Swal.fire({
            title: 'ลบไฟล์แนบ?',
            text: "ต้องการลบไฟล์แนบนี้ถาวรใช่หรือไม่?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'ลบไฟล์',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/ajax/documents.php',
                    type: 'POST',
                    data: { action: 'delete_attachment', attachment_id: dbId },
                    success: function(res) {
                        if (res.success) {
                            $(`.file-item[data-id="${uiId}"]`).remove();
                        }
                    }
                });
            }
        });
    } else {
        // Just remove from local array
        const index = selectedFiles.findIndex((f, i) => i == uiId); // This is a bit tricky with current implementation
        // For local files, we can just remove from UI
        $(`.file-item[data-id="${uiId}"]`).remove();
    }
}
</script>
