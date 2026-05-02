<?php
/**
 * edocument/memos.php
 * บันทึกข้อความ (Memos)
 */
session_start();
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['llw_role'])) { header('Location: /login.php'); exit; }
require_once __DIR__ . '/../includes/doc_module.php';
$pageTitle = 'บันทึกข้อความ (Memos)';
$activeSystem = 'edoc';
require_once __DIR__ . '/../components/layout_start.php';
?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<div class="row">
    <div class="col-12">
        <?php renderDocumentList('memo', ['title' => 'รายการบันทึกข้อความ', 'table' => 'edoc_memos']); ?>
    </div>
</div>
<?php renderDocumentModal('memo'); ?>
<?php require_once __DIR__ . '/../components/layout_end.php'; ?>
<script>
function editDoc(id) {
    $.ajax({
        url: '/ajax/documents.php',
        type: 'GET',
        data: { action: 'get', type: 'memo', id: id },
        success: function(res) {
            if (res.success) {
                const data = res.data;
                $('#docId').val(data.id);
                $('#doc_number').val(data.doc_number);
                $('#doc_date').val(data.doc_date);
                $('#subject').val(data.subject);
                $('#status').val(data.status);
                $('#year_be').val(data.year_be);
                $('#modalTitle').text('แก้ไขบันทึกข้อความ');
                fileList.innerHTML = '';
                if (data.attachments) {
                    data.attachments.forEach(att => {
                        renderFileItem({ id: att.id, name: att.file_name, size: att.file_size, path: att.file_path }, true);
                    });
                }
                loadUsers();
                $.get('/ajax/documents.php', { action: 'get_involved_users', ref_id: id, ref_table: 'edoc_memos' }, function(invRes) {
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
        text: "คุณต้องการลบเอกสารนี้ใช่หรือไม่?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'ลบข้อมูล'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '/ajax/documents.php',
                type: 'POST',
                data: { action: 'delete', type: 'memo', id: id },
                success: function(res) {
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'สำเร็จ', text: res.message, timer: 1500, showConfirmButton: false });
                        loadDocs();
                    }
                }
            });
        }
    });
}

function removeFile(uiId, dbId) {
    if (dbId) {
        $.ajax({
            url: '/ajax/documents.php',
            type: 'POST',
            data: { action: 'delete_attachment', attachment_id: dbId },
            success: function(res) {
                if (res.success) { $(`.file-item[data-id="${uiId}"]`).remove(); }
            }
        });
    } else {
        $(`.file-item[data-id="${uiId}"]`).remove();
    }
}
</script>
