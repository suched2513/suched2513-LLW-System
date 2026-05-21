/**
 * parent_meeting/assets/js/app.js
 * สคริปต์หลักสำหรับจัดการพฤติกรรม UI และการเชื่อมต่อ AJAX
 */

$(document).ready(function () {
    // 1. จัดการ Toggle Sidebar ค้นหาระหว่าง Mobile/Desktop
    $('#sidebarCollapse').on('click', function () {
        $('#sidebar').toggleClass('active');
    });

    // 2. กำหนดค่าเริ่มต้นภาษาไทยสำหรับ DataTables.js
    if ($.fn.dataTable) {
        $.extend(true, $.fn.dataTable.defaults, {
            language: {
                sProcessing: "กำลังดำเนินการ...",
                sLengthMenu: "แสดง _MENU_ แถว",
                sZeroRecords: "ไม่พบข้อมูลที่ต้องการ",
                sInfo: "แสดง _START_ ถึง _END_ จากทั้งหมด _TOTAL_ แถว",
                sInfoEmpty: "แสดง 0 ถึง 0 จากทั้งหมด 0 แถว",
                sInfoFiltered: "(กรองข้อมูล _MAX_ ทุกแถว)",
                sInfoPostFix: "",
                sSearch: "ค้นหาด่วน:",
                sUrl: "",
                oPaginate: {
                    sFirst: "เริ่มต้น",
                    sPrevious: "ก่อนหน้า",
                    sNext: "ถัดไป",
                    sLast: "สุดท้าย"
                }
            },
            pageLength: 10,
            responsive: true
        });
    }
});

// ฟังก์ชันเปิด Alert แจ้งเตือนด้วย SweetAlert2
function showAlert(title, text, icon = 'success') {
    return Swal.fire({
        title: title,
        text: text,
        icon: icon,
        confirmButtonColor: '#0d6efd',
        confirmButtonText: 'ตกลง'
    });
}

// ฟังก์ชันยืนยันการลบข้อมูล (Confirmation)
function confirmDelete(title, text, confirmCallback) {
    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'ยืนยันการลบ',
        cancelButtonText: 'ยกเลิก'
    }).then((result) => {
        if (result.isConfirmed) {
            confirmCallback();
        }
    });
}
