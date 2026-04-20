<?php
header('Content-Type: application/json');
echo json_encode([
    'mb_encodings' => mb_list_encodings(),
    'iconv_info' => function_exists('iconv') ? 'Available' : 'Missing'
]);
