<?php
session_start();
// Clear student-specific session keys only; staff session untouched if somehow both exist
foreach (['is_student','student_uid','student_code','student_name','student_class',
          'bus_student_id','bus_student_sid','bus_student_name','bus_student_class'] as $k) {
    unset($_SESSION[$k]);
}
header('Location: /student/login.php?bye=1'); exit();
