<?php
session_start();
unset(
    $_SESSION['bus_student_id'],
    $_SESSION['bus_student_sid'],
    $_SESSION['bus_student_name'],
    $_SESSION['bus_student_class']
);
header('Location: /bus/index.php?bye=1');
exit();
