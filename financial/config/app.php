<?php
/**
 * config/app.php
 * การตั้งค่าหลักของเว็บแอปพลิเคชันระบบบริหารการเงินและบัญชีสถานศึกษา
 */

return [
    'name' => $_ENV['APP_NAME'] ?? 'ระบบบริหารการเงินและบัญชีสถานศึกษา',
    'version' => '1.0.0',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url' => $_ENV['APP_URL'] ?? 'https://llw.krusuched.com/financial',
    'timezone' => 'Asia/Bangkok',
    'locale' => 'th',

    // ธีมสีราชการ (Government Professional Palette)
    'theme' => [
        'primary'   => '#0B2545', // สีน้ำเงินเข้มราชการ (Dark Navy Blue)
        'secondary' => '#134074', // สีน้ำเงินรอง (Royal Blue)
        'accent'    => '#C59B27', // สีทองพระราชทาน/เกียรติยศ (Royal Gold)
        'accent_light' => '#F4E8C1', // สีทองอ่อน (Soft Gold Accent)
        'bg_light'  => '#F8FAFC', // สีขาวสว่างพื้นหลัง (Clean Slate White)
        'text_dark' => '#1E293B', // สีตัวอักษรเข้ม
        'success'   => '#15803D', // สีเขียวผ่านเกณฑ์
        'warning'   => '#D97706', // สีส้มเตือน (>=80%)
        'danger'    => '#B91C1C', // สีแดงบล็อก/ยกเลิก (>=100%)
    ],

    // การตั้งค่าองค์กรเริ่มต้น
    'school' => [
        'name'        => $_ENV['SCHOOL_NAME'] ?? 'โรงเรียนละลมวิทยา',
        'affiliation' => $_ENV['SCHOOL_AFFILIATION'] ?? 'สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาศรีสะเกษ ยโสธร',
        'address'     => $_ENV['SCHOOL_ADDRESS'] ?? 'ตำบลละลม อำเภอไพรบึง จังหวัดศรีสะเกษ',
        'phone'       => $_ENV['SCHOOL_PHONE'] ?? '045-XXXXXX',
        'tax_id'      => $_ENV['SCHOOL_TAX_ID'] ?? '0994000XXXXXX',
    ],

    // การควบคุมงบประมาณ (Budget Control Thresholds)
    'budget_control' => [
        'warning_percent' => 80.0,  // เตือนเมื่อแตะ 80%
        'block_percent'   => 100.0, // บล็อกเมื่อแตะ 100%
        'allow_override'  => false, // นโยบายเริ่มต้นไม่อนุญาตให้เกินงบ
    ],
];
