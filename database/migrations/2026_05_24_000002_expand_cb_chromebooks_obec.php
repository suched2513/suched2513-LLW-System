<?php
return [
    'up' => function (PDO $pdo) {
        $cols = $pdo->query("SHOW COLUMNS FROM cb_chromebooks")->fetchAll(PDO::FETCH_COLUMN);

        $adds = [
            'brand'        => "ALTER TABLE cb_chromebooks ADD COLUMN brand VARCHAR(100) NULL AFTER model",
            'mac_address'  => "ALTER TABLE cb_chromebooks ADD COLUMN mac_address VARCHAR(50) NULL AFTER serial_number",
            'imei'         => "ALTER TABLE cb_chromebooks ADD COLUMN imei VARCHAR(30) NULL AFTER mac_address",
            'keyboard_box' => "ALTER TABLE cb_chromebooks ADD COLUMN keyboard_box VARCHAR(50) NULL AFTER imei",
            'keyboard_sn'  => "ALTER TABLE cb_chromebooks ADD COLUMN keyboard_sn VARCHAR(100) NULL AFTER keyboard_box",
            'sim_type'     => "ALTER TABLE cb_chromebooks ADD COLUMN sim_type VARCHAR(30) NULL AFTER keyboard_sn",
            'mobile_no'    => "ALTER TABLE cb_chromebooks ADD COLUMN mobile_no VARCHAR(30) NULL AFTER sim_type",
            'borrower_type'=> "ALTER TABLE cb_chromebooks ADD COLUMN borrower_type ENUM('Teacher','Student') NULL AFTER mobile_no",
            'borrower_name'=> "ALTER TABLE cb_chromebooks ADD COLUMN borrower_name VARCHAR(200) NULL AFTER borrower_type",
        ];
        foreach ($adds as $col => $sql) {
            if (!in_array($col, $cols)) {
                $pdo->exec($sql);
            }
        }
    },
    'down' => function (PDO $pdo) {
        foreach (['brand','mac_address','imei','keyboard_box','keyboard_sn','sim_type','mobile_no','borrower_type','borrower_name'] as $col) {
            $exists = $pdo->query("SHOW COLUMNS FROM cb_chromebooks LIKE '$col'")->fetchAll();
            if (!empty($exists)) {
                $pdo->exec("ALTER TABLE cb_chromebooks DROP COLUMN $col");
            }
        }
    },
];
