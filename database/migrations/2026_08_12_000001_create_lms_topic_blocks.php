<?php
return [
    'up' => function (PDO $pdo) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS lms_topic_blocks (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                topic_id      INT NOT NULL,
                order_no      INT NOT NULL DEFAULT 1,
                block_type    ENUM(
                                  'heading','text','image','video','audio','pdf','file',
                                  'callout_info','callout_example','callout_warning','callout_hint',
                                  'question','summary','link'
                              ) NOT NULL,
                title         VARCHAR(255) DEFAULT NULL,
                body          TEXT DEFAULT NULL,
                media_path    VARCHAR(255) DEFAULT NULL,
                media_url     VARCHAR(500) DEFAULT NULL,
                original_name VARCHAR(255) DEFAULT NULL,
                created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_topic_order (topic_id, order_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // ── Backfill: convert existing topic content/links/youtube/files into blocks ──
        // Idempotent: only runs for topics that have zero blocks so far.
        $hasBlocks = $pdo->prepare("SELECT COUNT(*) FROM lms_topic_blocks WHERE topic_id=?");

        $topics = $pdo->query("SELECT id, content FROM lms_topics")->fetchAll();
        $insBlock = $pdo->prepare("
            INSERT INTO lms_topic_blocks (topic_id, order_no, block_type, title, body, media_path, media_url, original_name)
            VALUES (?,?,?,?,?,?,?,?)
        ");

        foreach ($topics as $t) {
            $hasBlocks->execute([$t['id']]);
            if ((int)$hasBlocks->fetchColumn() > 0) continue;

            $order = 1;

            if (!empty($t['content'])) {
                $insBlock->execute([$t['id'], $order++, 'text', null, $t['content'], null, null, null]);
            }

            $links = $pdo->prepare("SELECT url, link_label FROM lms_topic_links WHERE topic_id=? ORDER BY id");
            $links->execute([$t['id']]);
            foreach ($links->fetchAll() as $lk) {
                $insBlock->execute([$t['id'], $order++, 'link', $lk['link_label'] ?: null, null, null, $lk['url'], null]);
            }

            $yts = $pdo->prepare("SELECT url, video_label FROM lms_topic_youtube WHERE topic_id=? ORDER BY id");
            $yts->execute([$t['id']]);
            foreach ($yts->fetchAll() as $yt) {
                $insBlock->execute([$t['id'], $order++, 'video', $yt['video_label'] ?: null, null, null, $yt['url'], null]);
            }

            $files = $pdo->prepare("SELECT filename, original_name FROM lms_topic_files WHERE topic_id=? ORDER BY id");
            $files->execute([$t['id']]);
            foreach ($files->fetchAll() as $f) {
                // Legacy files live under uploads/lms/ (see old topics.php saveTopic()); new blocks
                // always store media_path as a full path relative to the site root.
                $insBlock->execute([$t['id'], $order++, 'file', null, null, 'uploads/lms/' . $f['filename'], null, $f['original_name']]);
            }
        }
    },

    'down' => function (PDO $pdo) {
        $pdo->exec("DROP TABLE IF EXISTS lms_topic_blocks");
    },
];
