<?php
// lms/_helpers.php — shared ownership checks + activity log for the LMS module

// NOTE: a subject with teacher_id NULL (created by an admin without assigning
// a teacher yet) is intentionally NOT visible to regular teachers — only
// super_admin can see/manage it until it's assigned to someone via
// lms/subjects.php. Don't add "OR teacher_id IS NULL" back here; that
// previously let every teacher see and edit every unassigned subject.

function lms_get_owned_subject(PDO $pdo, int $subject_id, bool $is_admin, int $teacher_id): ?array {
    if (!$subject_id) return null;
    if ($is_admin) {
        $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE id=?");
        $st->execute([$subject_id]);
    } else {
        $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE id=? AND teacher_id=?");
        $st->execute([$subject_id, $teacher_id]);
    }
    $row = $st->fetch();
    return $row ?: null;
}

function lms_get_owned_unit(PDO $pdo, int $unit_id, bool $is_admin, int $teacher_id): ?array {
    if (!$unit_id) return null;
    $sql = "SELECT u.* FROM lms_units u JOIN lms_subjects s ON s.id = u.subject_id WHERE u.id=?";
    if (!$is_admin) $sql .= " AND s.teacher_id=?";
    $st = $pdo->prepare($sql);
    $is_admin ? $st->execute([$unit_id]) : $st->execute([$unit_id, $teacher_id]);
    $row = $st->fetch();
    return $row ?: null;
}

function lms_get_owned_topic(PDO $pdo, int $topic_id, bool $is_admin, int $teacher_id): ?array {
    if (!$topic_id) return null;
    $sql = "SELECT t.* FROM lms_topics t JOIN lms_units u ON u.id = t.unit_id JOIN lms_subjects s ON s.id = u.subject_id WHERE t.id=?";
    if (!$is_admin) $sql .= " AND s.teacher_id=?";
    $st = $pdo->prepare($sql);
    $is_admin ? $st->execute([$topic_id]) : $st->execute([$topic_id, $teacher_id]);
    $row = $st->fetch();
    return $row ?: null;
}

function lms_get_owned_exercise(PDO $pdo, int $exercise_id, bool $is_admin, int $teacher_id): ?array {
    if (!$exercise_id) return null;
    $sql = "SELECT e.*, u.subject_id FROM lms_unit_exercises e JOIN lms_units u ON u.id = e.unit_id JOIN lms_subjects s ON s.id = u.subject_id WHERE e.id=?";
    if (!$is_admin) $sql .= " AND s.teacher_id=?";
    $st = $pdo->prepare($sql);
    $is_admin ? $st->execute([$exercise_id]) : $st->execute([$exercise_id, $teacher_id]);
    $row = $st->fetch();
    return $row ?: null;
}

function lms_owned_subject_ids(PDO $pdo, bool $is_admin, int $teacher_id): array {
    if ($is_admin) {
        return $pdo->query("SELECT id FROM lms_subjects")->fetchAll(PDO::FETCH_COLUMN);
    }
    $st = $pdo->prepare("SELECT id FROM lms_subjects WHERE teacher_id=?");
    $st->execute([$teacher_id]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

function lms_block_types(): array {
    return [
        'heading'         => ['label' => 'หัวข้อ',                    'icon' => 'bi-type-h1'],
        'text'            => ['label' => 'ข้อความ',                   'icon' => 'bi-text-paragraph'],
        'image'           => ['label' => 'รูปภาพ',                    'icon' => 'bi-image'],
        'video'           => ['label' => 'วิดีโอ YouTube',            'icon' => 'bi-youtube'],
        'audio'           => ['label' => 'ไฟล์เสียง',                  'icon' => 'bi-file-earmark-music'],
        'pdf'             => ['label' => 'เอกสาร PDF',                'icon' => 'bi-file-earmark-pdf'],
        'file'            => ['label' => 'ไฟล์ดาวน์โหลด',              'icon' => 'bi-file-earmark-arrow-down'],
        'callout_info'    => ['label' => 'กล่องความรู้',               'icon' => 'bi-lightbulb'],
        'callout_example' => ['label' => 'ตัวอย่าง',                   'icon' => 'bi-list-check'],
        'callout_warning' => ['label' => 'คำเตือน',                    'icon' => 'bi-exclamation-triangle'],
        'callout_hint'    => ['label' => 'คำใบ้',                      'icon' => 'bi-question-circle'],
        'question'        => ['label' => 'คำถามแทรก (เฉลยกดดูได้)',    'icon' => 'bi-patch-question'],
        'summary'         => ['label' => 'สรุปท้ายบท',                 'icon' => 'bi-bookmark-check'],
        'link'            => ['label' => 'ลิงก์แหล่งเรียนรู้เพิ่มเติม',  'icon' => 'bi-link-45deg'],
    ];
}

function lms_render_topic_blocks(array $blocks): string {
    if (empty($blocks)) {
        return '<p class="text-slate-400 text-sm text-center py-6">ยังไม่มีเนื้อหา</p>';
    }
    $html = '';
    foreach ($blocks as $b) {
        $title = htmlspecialchars($b['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $body  = nl2br(htmlspecialchars($b['body'] ?? '', ENT_QUOTES, 'UTF-8'));
        $url   = htmlspecialchars($b['media_url'] ?? '', ENT_QUOTES, 'UTF-8');
        $path  = htmlspecialchars($b['media_path'] ?? '', ENT_QUOTES, 'UTF-8');
        $oname = htmlspecialchars($b['original_name'] ?? '', ENT_QUOTES, 'UTF-8');

        switch ($b['block_type']) {
            case 'heading':
                $html .= "<h3 class=\"text-base font-black text-slate-800 mt-2\">{$title}</h3>";
                break;
            case 'text':
                $html .= "<div class=\"text-sm text-slate-600 leading-relaxed\">{$body}</div>";
                break;
            case 'image':
                $html .= "<figure class=\"space-y-1\"><img src=\"/{$path}\" alt=\"{$title}\" class=\"w-full rounded-xl border border-slate-200\" loading=\"lazy\">"
                       . ($title ? "<figcaption class=\"text-xs text-slate-400 text-center\">{$title}</figcaption>" : '')
                       . "</figure>";
                break;
            case 'video':
                preg_match('/(?:v=|youtu\.be\/)([A-Za-z0-9_\-]{11})/', $b['media_url'] ?? '', $m);
                $vid = $m[1] ?? '';
                if ($vid) {
                    $html .= "<div class=\"rounded-2xl overflow-hidden border border-slate-200 bg-black\">"
                           . ($title ? "<div class=\"px-3 py-2 text-xs font-bold text-white bg-black/80\">{$title}</div>" : '')
                           . "<div class=\"relative\" style=\"padding-bottom:56.25%\"><iframe class=\"absolute inset-0 w-full h-full\" src=\"https://www.youtube.com/embed/{$vid}?rel=0\" frameborder=\"0\" allowfullscreen loading=\"lazy\"></iframe></div></div>";
                } else {
                    $html .= "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener\" class=\"flex items-center gap-2 bg-red-50 border border-red-100 rounded-xl px-3 py-2.5 text-sm text-red-600 font-bold\"><i class=\"bi bi-play-circle-fill\"></i><span class=\"truncate\">" . ($title ?: $url) . "</span></a>";
                }
                break;
            case 'audio':
                $html .= "<div class=\"space-y-1\">" . ($title ? "<p class=\"text-xs font-bold text-slate-500\">{$title}</p>" : '') . "<audio controls class=\"w-full\" src=\"/{$path}\"></audio></div>";
                break;
            case 'pdf':
                $html .= "<a href=\"/{$path}\" target=\"_blank\" rel=\"noopener\" class=\"flex items-center gap-2 bg-rose-50 border border-rose-100 rounded-xl px-3 py-2.5 text-sm text-rose-700 font-bold\"><i class=\"bi bi-file-earmark-pdf\"></i><span class=\"truncate\">" . ($title ?: $oname ?: 'เอกสาร PDF') . "</span></a>";
                break;
            case 'file':
                $html .= "<a href=\"/{$path}\" target=\"_blank\" download rel=\"noopener\" class=\"flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm text-slate-700 font-bold\"><i class=\"bi bi-file-earmark-arrow-down text-violet-500\"></i><span class=\"truncate\">" . ($oname ?: $title ?: 'ไฟล์แนบ') . "</span></a>";
                break;
            case 'callout_info':
                $html .= "<div class=\"rounded-xl border border-blue-100 bg-blue-50 p-3\"><p class=\"text-xs font-black text-blue-700 mb-1\"><i class=\"bi bi-lightbulb mr-1\"></i>" . ($title ?: 'กล่องความรู้') . "</p><div class=\"text-sm text-blue-900\">{$body}</div></div>";
                break;
            case 'callout_example':
                $html .= "<div class=\"rounded-xl border border-emerald-100 bg-emerald-50 p-3\"><p class=\"text-xs font-black text-emerald-700 mb-1\"><i class=\"bi bi-list-check mr-1\"></i>" . ($title ?: 'ตัวอย่าง') . "</p><div class=\"text-sm text-emerald-900\">{$body}</div></div>";
                break;
            case 'callout_warning':
                $html .= "<div class=\"rounded-xl border border-amber-200 bg-amber-50 p-3\"><p class=\"text-xs font-black text-amber-700 mb-1\"><i class=\"bi bi-exclamation-triangle mr-1\"></i>" . ($title ?: 'คำเตือน') . "</p><div class=\"text-sm text-amber-900\">{$body}</div></div>";
                break;
            case 'callout_hint':
                $html .= "<div class=\"rounded-xl border border-violet-100 bg-violet-50 p-3\"><p class=\"text-xs font-black text-violet-700 mb-1\"><i class=\"bi bi-question-circle mr-1\"></i>" . ($title ?: 'คำใบ้') . "</p><div class=\"text-sm text-violet-900\">{$body}</div></div>";
                break;
            case 'question':
                $qid = 'lmsq_' . (int)$b['id'];
                $html .= "<div class=\"rounded-xl border border-indigo-100 bg-indigo-50 p-3\">"
                       . "<p class=\"text-sm font-bold text-indigo-800\"><i class=\"bi bi-patch-question mr-1\"></i>{$title}</p>"
                       . "<button type=\"button\" onclick=\"document.getElementById('{$qid}').classList.toggle('hidden')\" class=\"mt-2 text-xs font-bold text-indigo-600 underline\">ดูเฉลย</button>"
                       . "<div id=\"{$qid}\" class=\"hidden mt-2 text-sm text-indigo-900 border-t border-indigo-100 pt-2\">{$body}</div>"
                       . "</div>";
                break;
            case 'summary':
                $html .= "<div class=\"rounded-xl border border-emerald-200 bg-emerald-50/60 p-3\"><p class=\"text-xs font-black text-emerald-700 mb-1\"><i class=\"bi bi-bookmark-check mr-1\"></i>สรุปท้ายบท</p><div class=\"text-sm text-emerald-900\">{$body}</div></div>";
                break;
            case 'link':
                $html .= "<a href=\"{$url}\" target=\"_blank\" rel=\"noopener\" class=\"flex items-center gap-2 bg-blue-50 border border-blue-100 rounded-xl px-3 py-2.5 text-sm text-blue-700 font-bold\"><i class=\"bi bi-box-arrow-up-right\"></i><span class=\"truncate\">" . ($title ?: $url) . "</span></a>";
                break;
        }
    }
    return '<div class="space-y-3">' . $html . '</div>';
}

// ── Question bank: types, rendering, grading ──────────────────────
function lms_question_types(): array {
    return [
        'choice'       => ['label' => 'ปรนัยหนึ่งคำตอบ',        'icon' => 'bi-record-circle',       'auto' => true],
        'multi_choice' => ['label' => 'ปรนัยหลายคำตอบ',          'icon' => 'bi-check2-square',       'auto' => true],
        'true_false'   => ['label' => 'ถูก–ผิด',                 'icon' => 'bi-toggle2-on',          'auto' => true],
        'fill_blank'   => ['label' => 'เติมคำ',                   'icon' => 'bi-input-cursor-text',   'auto' => true],
        'matching'     => ['label' => 'จับคู่',                   'icon' => 'bi-shuffle',             'auto' => true],
        'ordering'     => ['label' => 'เรียงลำดับ',                'icon' => 'bi-sort-numeric-down',   'auto' => true],
        'text'         => ['label' => 'อัตนัย (พิมพ์คำตอบ)',       'icon' => 'bi-pencil-square',       'auto' => false],
        'upload'       => ['label' => 'อัปโหลดไฟล์คำตอบ',         'icon' => 'bi-cloud-upload',        'auto' => false],
    ];
}

// Renders the exam-taking input widget for one question (student-facing).
// $choice_order: pre-shuffled [1,2,3,4] order, used for choice/multi_choice/true_false-style layouts.
function lms_render_exam_question_input(array $q, array $choice_order): string {
    $qtype = $q['question_type'] ?? 'choice';
    $qid   = (int)$q['id'];
    $esc   = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    switch ($qtype) {
        case 'text':
            return '<textarea name="q_' . $qid . '" rows="3" class="w-full mt-3 border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none resize-none focus:ring-2 focus:ring-violet-400" placeholder="พิมพ์คำตอบของคุณ..." data-type="text" data-qid="' . $qid . '"></textarea>';

        case 'upload':
            return '<div class="mt-3">
                <input type="file" name="qf_' . $qid . '" accept="image/*,application/pdf" class="hidden" id="qf_input_' . $qid . '" data-type="upload" data-qid="' . $qid . '" onchange="lmsMarkUploaded(' . $qid . ',this)">
                <label for="qf_input_' . $qid . '" class="flex items-center justify-center gap-2 w-full py-3 border-2 border-dashed border-violet-300 rounded-xl text-violet-500 font-bold text-sm cursor-pointer hover:bg-violet-50 transition-all">
                  <i class="bi bi-cloud-upload"></i> แนบไฟล์คำตอบ (รูปภาพ/PDF)
                </label>
                <p id="qf_name_' . $qid . '" class="text-xs text-slate-500 mt-1"></p>
              </div>';

        case 'fill_blank':
            return '<input type="text" name="q_' . $qid . '" class="w-full mt-3 border border-slate-200 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-violet-400" placeholder="พิมพ์คำตอบสั้นๆ..." data-type="fill_blank" data-qid="' . $qid . '">';

        case 'true_false': {
            $html = '<div class="grid grid-cols-2 gap-2 mt-3">';
            foreach ([1 => 'ถูก', 2 => 'ผิด'] as $n => $lbl) {
                $html .= '<label class="choice-label flex items-center justify-center gap-2 border border-slate-200 rounded-xl px-3 py-3 bg-slate-50 cursor-pointer">
                    <input type="radio" name="q_' . $qid . '" value="' . $n . '" class="hidden" data-type="choice" data-qid="' . $qid . '">
                    <span class="text-sm font-bold text-slate-700">' . $lbl . '</span>
                  </label>';
            }
            return $html . '</div>';
        }

        case 'choice':
        case 'multi_choice': {
            $isMulti   = $qtype === 'multi_choice';
            $inputType = $isMulti ? 'checkbox' : 'radio';
            $name      = $isMulti ? 'q_' . $qid . '[]' : 'q_' . $qid;
            $dtype     = $isMulti ? 'multi_choice' : 'choice';
            $html = '<div class="space-y-2 mt-3">';
            foreach ($choice_order as $n) {
                if (empty($q["choice{$n}"])) continue;
                $html .= '<label class="choice-label flex items-center gap-3 border border-slate-200 rounded-xl px-3 py-2.5 bg-slate-50">
                    <input type="' . $inputType . '" name="' . $name . '" value="' . $n . '" class="hidden" data-type="' . $dtype . '" data-qid="' . $qid . '">
                    <span class="choice-letter w-6 h-6 rounded-full bg-slate-200 text-slate-600 text-xs font-black flex items-center justify-center flex-shrink-0">' . $n . '</span>
                    <span class="text-sm text-slate-700">' . $esc($q["choice{$n}"]) . '</span>
                  </label>';
            }
            return $html . '</div>';
        }

        case 'matching': {
            $opts = json_decode($q['options_json'] ?? '', true) ?: ['left' => [], 'right' => []];
            $left = $opts['left'] ?? []; $right = $opts['right'] ?? [];
            $shuffledRightIdx = array_keys($right); shuffle($shuffledRightIdx);
            $html = '<div class="space-y-2 mt-3">';
            foreach ($left as $li => $ltext) {
                $html .= '<div class="flex items-center gap-2 border border-slate-200 rounded-xl px-3 py-2 bg-slate-50">
                    <span class="flex-1 text-sm text-slate-700">' . $esc($ltext) . '</span>
                    <i class="bi bi-arrow-right text-slate-300 flex-shrink-0"></i>
                    <select name="q_' . $qid . '[' . (int)$li . ']" class="flex-1 border border-slate-200 rounded-lg px-2 py-1.5 text-sm bg-white" data-type="matching" data-qid="' . $qid . '">
                      <option value="">— เลือกคู่ —</option>';
                foreach ($shuffledRightIdx as $ri) {
                    $html .= '<option value="' . (int)$ri . '">' . $esc($right[$ri]) . '</option>';
                }
                $html .= '</select></div>';
            }
            return $html . '</div>';
        }

        case 'ordering': {
            $items = json_decode($q['options_json'] ?? '', true) ?: [];
            $shuffledIdx = array_keys($items); shuffle($shuffledIdx);
            $n = count($items);
            $html = '<div class="space-y-2 mt-3">';
            foreach ($shuffledIdx as $origIdx) {
                $html .= '<div class="flex items-center gap-2 border border-slate-200 rounded-xl px-3 py-2 bg-slate-50">
                    <select name="q_' . $qid . '[' . (int)$origIdx . ']" class="border border-slate-200 rounded-lg px-2 py-1.5 text-sm bg-white w-20 flex-shrink-0" data-type="ordering" data-qid="' . $qid . '">
                      <option value="">-</option>';
                for ($r = 1; $r <= $n; $r++) $html .= '<option value="' . $r . '">' . $r . '</option>';
                $html .= '</select>
                    <span class="flex-1 text-sm text-slate-700">' . $esc($items[$origIdx]) . '</span>
                  </div>';
            }
            return $html . '</div>';
        }
    }
    return '';
}

// Grades one question against $_POST (auto-gradable types only). Reads $_POST directly —
// caller must submit the standard `q_{id}` / `q_{id}[]` field names produced by the renderer above.
function lms_grade_exam_answer(array $q): array {
    $qtype = $q['question_type'] ?? 'choice';
    $qid   = (int)$q['id'];
    $key   = 'q_' . $qid;

    switch ($qtype) {
        case 'choice':
        case 'true_false':
            $chosen = (int)($_POST[$key] ?? 0);
            return ['auto' => true, 'correct' => ($chosen === (int)$q['correct_answer']), 'chosen' => $chosen];

        case 'multi_choice':
            $chosen = array_map('intval', $_POST[$key] ?? []);
            sort($chosen);
            $correct = array_map('intval', json_decode($q['correct_json'] ?? '[]', true) ?: []);
            sort($correct);
            return ['auto' => true, 'correct' => (!empty($correct) && $chosen === $correct), 'chosen' => $chosen];

        case 'fill_blank':
            $raw = trim($_POST[$key] ?? '');
            $val = mb_strtolower($raw, 'UTF-8');
            $accepted = array_map(fn($a) => mb_strtolower(trim($a), 'UTF-8'), json_decode($q['correct_json'] ?? '[]', true) ?: []);
            return ['auto' => true, 'correct' => ($val !== '' && in_array($val, $accepted, true)), 'chosen' => $raw];

        case 'matching': {
            $opts = json_decode($q['options_json'] ?? '', true) ?: ['left' => []];
            $leftCount = count($opts['left'] ?? []);
            $posted = $_POST[$key] ?? [];
            $ok = $leftCount > 0;
            for ($i = 0; $i < $leftCount; $i++) {
                if ((string)($posted[$i] ?? '') !== (string)$i) { $ok = false; break; }
            }
            return ['auto' => true, 'correct' => $ok, 'chosen' => $posted];
        }

        case 'ordering': {
            $items = json_decode($q['options_json'] ?? '', true) ?: [];
            $n = count($items);
            $posted = $_POST[$key] ?? [];
            $ok = $n > 0;
            for ($i = 0; $i < $n; $i++) {
                if ((int)($posted[$i] ?? -1) !== $i + 1) { $ok = false; break; }
            }
            return ['auto' => true, 'correct' => $ok, 'chosen' => $posted];
        }

        case 'text':
            return ['auto' => false, 'correct' => null, 'text' => trim($_POST[$key] ?? '')];

        case 'upload':
            return ['auto' => false, 'correct' => null, 'text' => null];
    }
    return ['auto' => true, 'correct' => false];
}

// Renders the post-submission review block for one question (student-facing "เฉลย" screen).
// $ans is the ['auto','correct','chosen'|'text'|'file_path'] structure produced when grading.
function lms_render_exam_result_review(array $q, ?array $ans): string {
    $qtype = $q['question_type'] ?? 'choice';
    $esc   = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

    switch ($qtype) {
        case 'text':
            return '<div class="rounded-xl bg-slate-50 border border-slate-200 px-3 py-2 text-sm text-slate-600">' . nl2br($esc($ans['text'] ?? '—')) . '</div>';

        case 'upload':
            if (!empty($ans['file_path'])) {
                return '<a href="/' . $esc($ans['file_path']) . '" target="_blank" rel="noopener" class="flex items-center gap-2 text-sm text-blue-600 font-bold underline"><i class="bi bi-file-earmark-arrow-down"></i>ดูไฟล์ที่ส่ง</a>';
            }
            return '<p class="text-xs text-slate-400">— ยังไม่ได้แนบไฟล์ —</p>';

        case 'choice':
        case 'true_false': {
            $chosen = (int)($ans['chosen'] ?? 0);
            $isTf   = $qtype === 'true_false';
            $opts   = $isTf ? [1 => 'ถูก', 2 => 'ผิด'] : [1 => $q['choice1'] ?? '', 2 => $q['choice2'] ?? '', 3 => $q['choice3'] ?? '', 4 => $q['choice4'] ?? '', 5 => $q['choice5'] ?? ''];
            $html = '<div class="space-y-1.5">';
            foreach ($opts as $n => $label) {
                if ($label === '' || $label === null) continue;
                $is_right = (int)$q['correct_answer'] === $n;
                $is_chosen = $chosen === $n;
                $cls = 'border rounded-xl px-3 py-2 text-xs flex items-center gap-2 ';
                $cls .= $is_right ? 'bg-emerald-50 border-emerald-300 text-emerald-700 font-bold' : ($is_chosen ? 'bg-rose-50 border-rose-300 text-rose-600' : 'bg-slate-50 border-slate-100 text-slate-500');
                $html .= '<div class="' . $cls . '"><span class="w-5 h-5 rounded-full text-center leading-5 text-[10px] font-black flex-shrink-0 ' . ($is_right ? 'bg-emerald-500 text-white' : ($is_chosen ? 'bg-rose-400 text-white' : 'bg-slate-200 text-slate-500')) . '">' . $n . '</span>' . $esc($label);
                if ($is_right) $html .= '<i class="bi bi-check-circle-fill text-emerald-500 ml-auto"></i>';
                if ($is_chosen && !$is_right) $html .= '<i class="bi bi-x-circle-fill text-rose-400 ml-auto"></i>';
                $html .= '</div>';
            }
            return $html . '</div>';
        }

        case 'multi_choice': {
            $chosen  = array_map('intval', $ans['chosen'] ?? []);
            $correct = array_map('intval', json_decode($q['correct_json'] ?? '[]', true) ?: []);
            $html = '<div class="space-y-1.5">';
            for ($n = 1; $n <= 5; $n++) {
                if (empty($q["choice{$n}"])) continue;
                $is_right  = in_array($n, $correct, true);
                $is_chosen = in_array($n, $chosen, true);
                $cls = 'border rounded-xl px-3 py-2 text-xs flex items-center gap-2 ';
                $cls .= $is_right ? 'bg-emerald-50 border-emerald-300 text-emerald-700 font-bold' : ($is_chosen ? 'bg-rose-50 border-rose-300 text-rose-600' : 'bg-slate-50 border-slate-100 text-slate-500');
                $html .= '<div class="' . $cls . '"><span class="w-5 h-5 rounded-lg text-center leading-5 text-[10px] font-black flex-shrink-0 ' . ($is_right ? 'bg-emerald-500 text-white' : ($is_chosen ? 'bg-rose-400 text-white' : 'bg-slate-200 text-slate-500')) . '">' . $n . '</span>' . $esc($q["choice{$n}"]);
                if ($is_right) $html .= '<i class="bi bi-check-circle-fill text-emerald-500 ml-auto"></i>';
                if ($is_chosen && !$is_right) $html .= '<i class="bi bi-x-circle-fill text-rose-400 ml-auto"></i>';
                $html .= '</div>';
            }
            return $html . '</div>';
        }

        case 'fill_blank': {
            $accepted = json_decode($q['correct_json'] ?? '[]', true) ?: [];
            return '<p class="text-sm text-slate-700"><span class="font-bold">คำตอบของคุณ:</span> ' . $esc($ans['chosen'] ?? '—') . '</p>'
                 . '<p class="text-xs text-slate-400 mt-1">เฉลยที่ยอมรับ: ' . $esc(implode(', ', $accepted)) . '</p>';
        }

        case 'matching': {
            $opts   = json_decode($q['options_json'] ?? '', true) ?: ['left' => [], 'right' => []];
            $posted = $ans['chosen'] ?? [];
            $html = '<div class="space-y-1.5">';
            foreach ($opts['left'] as $li => $ltext) {
                $picked      = $posted[$li] ?? null;
                $is_right    = $picked !== null && (int)$picked === (int)$li;
                $pickedText  = ($picked !== null && isset($opts['right'][$picked])) ? $opts['right'][$picked] : '—';
                $html .= '<div class="border rounded-xl px-3 py-2 text-xs ' . ($is_right ? 'bg-emerald-50 border-emerald-300 text-emerald-700' : 'bg-rose-50 border-rose-300 text-rose-600') . '">'
                       . $esc($ltext) . ' → ' . $esc($pickedText)
                       . ($is_right ? ' <i class="bi bi-check-circle-fill text-emerald-500"></i>' : ' <i class="bi bi-x-circle-fill text-rose-400"></i> <span class="text-slate-400">(เฉลย: ' . $esc($opts['right'][$li] ?? '') . ')</span>')
                       . '</div>';
            }
            return $html . '</div>';
        }

        case 'ordering': {
            $items  = json_decode($q['options_json'] ?? '', true) ?: [];
            $posted = $ans['chosen'] ?? [];
            $html = '<div class="space-y-1.5">';
            foreach ($items as $idx => $text) {
                $rank     = (int)($posted[$idx] ?? 0);
                $is_right = $rank === $idx + 1;
                $html .= '<div class="border rounded-xl px-3 py-2 text-xs flex items-center gap-2 ' . ($is_right ? 'bg-emerald-50 border-emerald-300 text-emerald-700' : 'bg-rose-50 border-rose-300 text-rose-600') . '">'
                       . '<span class="w-5 h-5 rounded-full text-center leading-5 text-[10px] font-black flex-shrink-0 bg-slate-200 text-slate-600">' . ($rank ?: '-') . '</span>'
                       . $esc($text) . (!$is_right ? ' <span class="text-slate-400">(ที่ถูก: อันดับ ' . ($idx + 1) . ')</span>' : '')
                       . '</div>';
            }
            return $html . '</div>';
        }
    }
    return '';
}

// Shared client-side "answered count" logic — echoed verbatim into both exam pages' <script> blocks.
function lms_exam_js_helpers(): string {
    return <<<'JS'
function lmsMarkUploaded(qid, input) {
  const p = document.getElementById('qf_name_' + qid);
  if (p) p.textContent = input.files.length ? ('แนบไฟล์: ' + input.files[0].name) : '';
  if (typeof countAnswered === 'function') countAnswered();
}
function lmsCountAnsweredQids() {
  const qids = new Set();
  document.querySelectorAll('[data-qid]').forEach(el => qids.add(el.dataset.qid));
  let cnt = 0;
  qids.forEach(qid => {
    const choiceIn = document.querySelectorAll(`input[data-qid="${qid}"][data-type="choice"]`);
    if (choiceIn.length) { if ([...choiceIn].some(i => i.checked)) cnt++; return; }
    const multiIn = document.querySelectorAll(`input[data-qid="${qid}"][data-type="multi_choice"]`);
    if (multiIn.length) { if ([...multiIn].some(i => i.checked)) cnt++; return; }
    const uploadIn = document.querySelector(`input[data-qid="${qid}"][data-type="upload"]`);
    if (uploadIn) { if (uploadIn.files && uploadIn.files.length) cnt++; return; }
    const matchSel = document.querySelectorAll(`select[data-qid="${qid}"][data-type="matching"]`);
    if (matchSel.length) { if ([...matchSel].every(s => s.value !== '')) cnt++; return; }
    const orderSel = document.querySelectorAll(`select[data-qid="${qid}"][data-type="ordering"]`);
    if (orderSel.length) {
      const vals = [...orderSel].map(s => s.value);
      if (vals.every(v => v !== '') && new Set(vals).size === vals.length) cnt++;
      return;
    }
    const textEl = document.querySelector(`textarea[data-qid="${qid}"][data-type="text"], input[data-qid="${qid}"][data-type="fill_blank"]`);
    if (textEl && textEl.value.trim()) cnt++;
  });
  return cnt;
}
// Soft anti-cheat: detect when the exam tab/window loses focus (tab switch, app
// switch, minimize) and log a count into a hidden field — warns the student and
// lets the teacher review it later, since a web page can't truly block this.
// Ignores the brief visibility loss a native file-picker dialog causes on upload
// questions, so attaching a photo doesn't get flagged as leaving the screen.
function lmsInitTabSwitchGuard() {
  let count = 0;
  let filePickerOpen = false;
  document.querySelectorAll('input[type=file]').forEach(el => {
    el.addEventListener('click', () => { filePickerOpen = true; setTimeout(() => { filePickerOpen = false; }, 3000); });
  });
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden || filePickerOpen) return;
    count++;
    const input = document.getElementById('tab_switch_count');
    if (input) input.value = count;
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        icon: 'warning', title: 'ออกนอกหน้าจอระหว่างสอบ!',
        text: 'ระบบบันทึกไว้แล้ว (ครั้งที่ ' + count + ') กรุณาอยู่ในหน้าสอบจนกว่าจะส่งคำตอบ',
        confirmButtonColor: '#7C3AED', timer: 3500, showConfirmButton: false,
      });
    }
  });
}
JS;
}

// Shared admin question-editor JS (the modal-field builder) — echoed into pre_exam.php,
// post_exam.php, midterm_exam.php, final_exam.php. choice 5 is optional — leave it blank
// to keep a 4-choice question. openAddModal()/openEdit() stay page-local since each page
// wires up slightly different form element ids (e.g. post_exam.php has no question image).
function lms_exam_admin_js_helpers(): string {
    return <<<'JS'
function openModal(id){const el=document.getElementById(id);el.classList.remove('hidden');el.classList.add('flex');}
function closeModal(id){const el=document.getElementById(id);el.classList.add('hidden');el.classList.remove('flex');}

function qFieldsHtml(type, vals) {
  vals = vals || {};
  const esc = s => String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  if (type === 'choice' || type === 'multi_choice') {
    let h = '';
    for (let n=1;n<=5;n++) {
      const optional = n === 5;
      h += `<div class="mb-3"><label class="block text-xs font-black text-slate-500 mb-1">ตัวเลือกที่ ${n} ${optional ? '<span class="text-slate-400 font-normal">(ไม่บังคับ — สำหรับข้อสอบ 5 ตัวเลือก)</span>' : '<span class="text-rose-500">*</span>'}</label>
        <input type="text" name="choice${n}" value="${esc(vals['choice'+n])}" ${optional ? '' : 'required'} class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none"></div>`;
    }
    if (type === 'choice') {
      const cur = vals.correct_answer || 1;
      h += `<div><label class="block text-xs font-black text-slate-500 mb-1">เฉลย <span class="text-rose-500">*</span></label>
        <select name="correct_answer" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none">`;
      for (let n=1;n<=5;n++) h += `<option value="${n}" ${cur==n?'selected':''}>ข้อ ${n}</option>`;
      h += `</select></div>`;
    } else {
      const correct = vals.correct_json ? JSON.parse(vals.correct_json) : [];
      h += `<div><label class="block text-xs font-black text-slate-500 mb-2">เฉลย (เลือกได้หลายข้อ) <span class="text-rose-500">*</span></label><div class="flex gap-4 flex-wrap">`;
      for (let n=1;n<=5;n++) h += `<label class="flex items-center gap-1.5"><input type="checkbox" name="correct_multi[]" value="${n}" class="accent-violet-600" ${correct.includes(n)?'checked':''}><span class="text-sm">ข้อ ${n}</span></label>`;
      h += `</div></div>`;
    }
    return h;
  }
  if (type === 'true_false') {
    const cur = vals.correct_answer || 1;
    return `<div><label class="block text-xs font-black text-slate-500 mb-2">เฉลย <span class="text-rose-500">*</span></label>
      <div class="flex gap-4">
        <label class="flex items-center gap-2"><input type="radio" name="tf_answer" value="1" class="accent-violet-600" ${cur==1?'checked':''}><span class="text-sm font-bold">ถูก</span></label>
        <label class="flex items-center gap-2"><input type="radio" name="tf_answer" value="2" class="accent-violet-600" ${cur==2?'checked':''}><span class="text-sm font-bold">ผิด</span></label>
      </div></div>`;
  }
  if (type === 'fill_blank') {
    const accepted = vals.correct_json ? JSON.parse(vals.correct_json).join('\n') : '';
    return `<div><label class="block text-xs font-black text-slate-500 mb-1">คำตอบที่ยอมรับ (บรรทัดละ 1 คำตอบ) <span class="text-rose-500">*</span></label>
      <textarea name="fill_answers" rows="3" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-violet-400 outline-none resize-none" placeholder="กรุงเทพ&#10;กรุงเทพมหานคร">${esc(accepted)}</textarea></div>`;
  }
  if (type === 'matching') {
    let left = '', right = '';
    if (vals.options_json) { const o = JSON.parse(vals.options_json); left = (o.left||[]).join('\n'); right = (o.right||[]).join('\n'); }
    return `<div class="grid grid-cols-2 gap-3">
      <div><label class="block text-xs font-black text-slate-500 mb-1">รายการซ้าย <span class="text-rose-500">*</span></label>
        <textarea name="matching_left" rows="4" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm resize-none" placeholder="แมว&#10;หมา">${esc(left)}</textarea></div>
      <div><label class="block text-xs font-black text-slate-500 mb-1">รายการขวา (คู่ที่ถูกต้องเรียงตรงกับซ้าย) <span class="text-rose-500">*</span></label>
        <textarea name="matching_right" rows="4" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm resize-none" placeholder="Cat&#10;Dog">${esc(right)}</textarea></div>
      </div><p class="text-[10px] text-slate-400 mt-1">บรรทัดที่ 1 ของซ้ายจะจับคู่กับบรรทัดที่ 1 ของขวาโดยอัตโนมัติ</p>`;
  }
  if (type === 'ordering') {
    const items = vals.options_json ? JSON.parse(vals.options_json).join('\n') : '';
    return `<div><label class="block text-xs font-black text-slate-500 mb-1">รายการ เรียงตามลำดับที่ถูกต้อง (บรรทัดละ 1 รายการ) <span class="text-rose-500">*</span></label>
      <textarea name="ordering_items" rows="4" required class="w-full border border-slate-200 rounded-xl px-3 py-2 text-sm resize-none" placeholder="ล้างมือ&#10;หั่นผัก&#10;ผัดผัก&#10;จัดจาน">${esc(items)}</textarea></div>`;
  }
  if (type === 'upload') {
    return `<p class="text-xs text-slate-400 bg-slate-50 border border-slate-100 rounded-xl p-3"><i class="fas fa-info-circle mr-1"></i>นักเรียนจะแนบไฟล์ (รูปภาพ/PDF) เป็นคำตอบ ครูต้องตรวจให้คะแนนเอง</p>`;
  }
  return `<p class="text-xs text-slate-400 bg-slate-50 border border-slate-100 rounded-xl p-3"><i class="fas fa-info-circle mr-1"></i>นักเรียนพิมพ์คำตอบอิสระ ครูต้องตรวจให้คะแนนเอง</p>`;
}

function renderQFields(prefix, vals) {
  const type = document.getElementById(prefix+'_qtype').value;
  document.getElementById(prefix+'_fields').innerHTML = qFieldsHtml(type, vals || {});
  const hidden = document.getElementById(prefix+'_qtype_hidden');
  if (hidden) hidden.value = type;
}

function confirmDel(url){
  Swal.fire({icon:'warning',title:'ลบข้อสอบนี้?',showCancelButton:true,confirmButtonColor:'#ef4444',cancelButtonText:'ยกเลิก',confirmButtonText:'ลบ'})
    .then(r=>{if(r.isConfirmed)location.href=url;});
}
JS;
}

function lms_log_activity(PDO $pdo, string $action, string $targetType, int $targetId, $old = null, $new = null): void {
    try {
        $userId = $_SESSION['user_id'] ?? null;
        $pdo->prepare("INSERT INTO audit_logs (user_id, action, target_type, target_id, old_value, new_value, ip_address) VALUES (?,?,?,?,?,?,?)")
            ->execute([
                $userId,
                $action,
                $targetType,
                $targetId,
                $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
    } catch (Exception $e) {
        error_log('[LMS] activity log failed: ' . $e->getMessage());
    }
}


function lms_ensure_exercise_columns(PDO $pdo): bool {
    static $done = null;
    if ($done !== null) return $done;
    try {
        $hasTbl = (bool)$pdo->query("SHOW TABLES LIKE 'lms_unit_exercises'")->fetchColumn();
        if ($hasTbl) {
            $cols = $pdo->query("SHOW COLUMNS FROM `lms_unit_exercises`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('attachment_path', $cols, true)) {
                $pdo->exec("ALTER TABLE `lms_unit_exercises` ADD COLUMN `attachment_path` VARCHAR(255) NULL DEFAULT NULL AFTER `description`");
            }
            if (!in_array('attachment_name', $cols, true)) {
                $pdo->exec("ALTER TABLE `lms_unit_exercises` ADD COLUMN `attachment_name` VARCHAR(255) NULL DEFAULT NULL AFTER `attachment_path`");
            }
            if (!in_array('attachment_url', $cols, true)) {
                $pdo->exec("ALTER TABLE `lms_unit_exercises` ADD COLUMN `attachment_url` TEXT NULL DEFAULT NULL AFTER `attachment_name`");
            }
        }
        $done = true;
    } catch (Throwable $e) {
        error_log('[LMS] ensure exercise columns failed: ' . $e->getMessage());
        $done = false;
    }
    return $done;
}

// ── Semester & Academic Year helpers for LMS ─────────────────────────────
function lms_ensure_semester_columns(PDO $pdo): bool {
    static $done = null;
    if ($done !== null) return $done;
    try {
        if (function_exists('llw_ensure_semester_schema')) {
            llw_ensure_semester_schema($pdo);
        }
        $cols = $pdo->query("SHOW COLUMNS FROM `lms_subjects`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('academic_year', $cols, true)) {
            $pdo->exec("ALTER TABLE `lms_subjects` ADD COLUMN `academic_year` INT NOT NULL DEFAULT 2569");
        }
        if (!in_array('semester', $cols, true)) {
            $pdo->exec("ALTER TABLE `lms_subjects` ADD COLUMN `semester` TINYINT NOT NULL DEFAULT 1");
        }
        lms_ensure_exercise_columns($pdo);
        $done = true;
    } catch (Throwable $e) {
        error_log('[LMS] ensure semester columns failed: ' . $e->getMessage());
        $done = false;
    }
    return $done;
}

function lms_active_term_context(PDO $pdo): array {
    lms_ensure_semester_columns($pdo);
    $sysYear = function_exists('llw_current_academic_year') ? llw_current_academic_year() : ((int)date('Y') + 543);
    $sysSem  = function_exists('llw_current_semester') ? llw_current_semester() : 1;

    // Handle Super Admin 1-click "set system semester" action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['lms_term_action'] ?? '') === 'set_system_semester') {
        $isAdmin    = ($_SESSION['llw_role'] ?? '') === 'super_admin';
        $targetSem  = (int)($_POST['target_semester'] ?? 2);
        $targetYear = (int)($_POST['target_year'] ?? $sysYear);
        if ($isAdmin && in_array($targetSem, [1, 2], true)) {
            try {
                $pdo->exec("UPDATE llw_academic_terms SET is_active = 0");
                $st = $pdo->prepare("UPDATE llw_academic_terms SET is_active = 1 WHERE academic_year = ? AND semester = ?");
                $st->execute([$targetYear, $targetSem]);
                if ($st->rowCount() === 0 && function_exists('llw_default_term_dates')) {
                    $def = llw_default_term_dates($targetYear, $targetSem);
                    $pdo->prepare("INSERT INTO llw_academic_terms (academic_year, semester, start_date, end_date, is_active) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE is_active=1")
                        ->execute([$targetYear, $targetSem, $def['start_date'], $def['end_date']]);
                }
                $_SESSION['lms_selected_semester'] = $targetSem;
                $_SESSION['lms_selected_year']     = $targetYear;
            } catch (Throwable $e) {
                error_log('[LMS] set_system_semester failed: ' . $e->getMessage());
            }
        }
        $redirect = strtok($_SERVER['REQUEST_URI'], '?');
        $qs = $_GET;
        $qs['semester'] = $targetSem;
        $qs['year']     = $targetYear;
        header('Location: ' . $redirect . '?' . http_build_query($qs));
        exit();
    }

    if (isset($_GET['semester']) && in_array((int)$_GET['semester'], [1, 2], true)) {
        $_SESSION['lms_selected_semester'] = (int)$_GET['semester'];
    }
    if (isset($_GET['year']) && (int)$_GET['year'] >= 2565) {
        $_SESSION['lms_selected_year'] = (int)$_GET['year'];
    }

    $selSem  = (int)($_GET['semester'] ?? $_SESSION['lms_selected_semester'] ?? $sysSem);
    if (!in_array($selSem, [1, 2], true)) $selSem = $sysSem;
    $selYear = (int)($_GET['year'] ?? $_SESSION['lms_selected_year'] ?? $sysYear);

    // If admin switched system semester globally, sync default session unless user explicitly clicked ?semester=
    if (!isset($_GET['semester']) && isset($_SESSION['lms_last_sys_sem']) && $_SESSION['lms_last_sys_sem'] !== $sysSem) {
        $selSem = $sysSem;
        $_SESSION['lms_selected_semester'] = $sysSem;
    }
    $_SESSION['lms_last_sys_sem'] = $sysSem;

    return [
        'year'     => $selYear,
        'semester' => $selSem,
        'sys_year' => $sysYear,
        'sys_sem'  => $sysSem,
    ];
}

function lms_get_subjects_for_term(PDO $pdo, bool $is_admin, int $teacher_id, int $semester, int $academic_year): array {
    $hasSem = lms_ensure_semester_columns($pdo);
    $hasTid = (bool)$pdo->query("SHOW COLUMNS FROM `lms_subjects` LIKE 'teacher_id'")->fetch();

    if ($hasSem) {
        if (!$is_admin && $hasTid) {
            $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE teacher_id=? AND semester=? AND academic_year=? ORDER BY subject_name");
            $st->execute([$teacher_id, $semester, $academic_year]);
            return $st->fetchAll();
        }
        $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE semester=? AND academic_year=? ORDER BY subject_name");
        $st->execute([$semester, $academic_year]);
        return $st->fetchAll();
    }

    if (!$is_admin && $hasTid) {
        $st = $pdo->prepare("SELECT * FROM lms_subjects WHERE teacher_id=? ORDER BY subject_name");
        $st->execute([$teacher_id]);
        return $st->fetchAll();
    }
    return $pdo->query("SELECT * FROM lms_subjects ORDER BY subject_name")->fetchAll();
}

function lms_render_semester_bar(array $termCtx, bool $is_admin, array $extraQuery = []): string {
    $year   = (int)$termCtx['year'];
    $sem    = (int)$termCtx['semester'];
    $sysSem = (int)$termCtx['sys_sem'];

    $baseQs = array_merge($_GET, $extraQuery);
    unset($baseQs['msg'], $baseQs['done'], $baseQs['err']);

    $qs1 = http_build_query(array_merge($baseQs, ['semester' => 1, 'year' => $year]));
    $qs2 = http_build_query(array_merge($baseQs, ['semester' => 2, 'year' => $year]));

    $btn1Cls = $sem === 1
        ? 'bg-violet-600 text-white shadow-md shadow-violet-200 border-violet-600'
        : 'bg-white text-slate-600 border-slate-200 hover:border-violet-300 hover:text-violet-700';
    $btn2Cls = $sem === 2
        ? 'bg-violet-600 text-white shadow-md shadow-violet-200 border-violet-600'
        : 'bg-white text-slate-600 border-slate-200 hover:border-violet-300 hover:text-violet-700';

    $badge1 = $sysSem === 1 ? '<span class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-black ' . ($sem === 1 ? 'bg-white/25 text-white' : 'bg-emerald-100 text-emerald-700') . '">เทอมปัจจุบัน</span>' : '';
    $badge2 = $sysSem === 2 ? '<span class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-black ' . ($sem === 2 ? 'bg-white/25 text-white' : 'bg-emerald-100 text-emerald-700') . '">เทอมปัจจุบัน</span>' : '';

    $adminBtn = '';
    if ($is_admin && $sysSem !== 2) {
        $adminBtn = '
        <form method="POST" class="inline-block ml-auto">
            <input type="hidden" name="lms_term_action" value="set_system_semester">
            <input type="hidden" name="target_semester" value="2">
            <input type="hidden" name="target_year" value="' . $year . '">
            <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                <i class="fas fa-bolt"></i> ตั้ง "ภาคเรียนที่ 2" เป็นเทอมปัจจุบันของระบบทันที
            </button>
        </form>';
    } elseif ($is_admin && $sysSem === 2 && $sem === 1) {
        $adminBtn = '<span class="ml-auto text-[11px] font-bold text-amber-600 bg-amber-50 border border-amber-200 px-3 py-1 rounded-xl"><i class="fas fa-history mr-1"></i>กำลังดูข้อมูลย้อนหลังภาคเรียนที่ 1 (ระบบปัจจุบันคือเทอม 2)</span>';
    }

    return '
    <div class="bg-white rounded-2xl border border-violet-100 shadow-sm px-4 py-3 mb-5 flex flex-wrap items-center gap-2.5">
        <div class="flex items-center gap-2 mr-1">
            <span class="w-8 h-8 rounded-xl bg-violet-100 text-violet-700 flex items-center justify-center text-sm font-black">
                <i class="fas fa-calendar-alt"></i>
            </span>
            <div>
                <div class="text-[10px] font-black text-slate-400 uppercase tracking-wider leading-none">ปีการศึกษา ' . $year . '</div>
                <div class="text-xs font-black text-violet-700 mt-0.5">กำลังจัดการ: ภาคเรียนที่ ' . $sem . '/' . $year . '</div>
            </div>
        </div>
        <div class="flex items-center gap-1.5 flex-wrap">
            <a href="?' . htmlspecialchars($qs1, ENT_QUOTES, 'UTF-8') . '" class="px-3.5 py-1.5 rounded-xl text-xs font-black border transition-all flex items-center ' . $btn1Cls . '">
                <i class="fas fa-1 mr-1"></i> ภาคเรียนที่ 1' . $badge1 . '
            </a>
            <a href="?' . htmlspecialchars($qs2, ENT_QUOTES, 'UTF-8') . '" class="px-3.5 py-1.5 rounded-xl text-xs font-black border transition-all flex items-center ' . $btn2Cls . '">
                <i class="fas fa-2 mr-1"></i> ภาคเรียนที่ 2' . $badge2 . '
            </a>
        </div>
        ' . $adminBtn . '
    </div>';
}

/**
 * Deep-clone an LMS subject from Semester 1 into Semester 2 (or any target semester)
 * Copies: subject info, classrooms, subject_settings, units, topics, topic_blocks, exercises (without student submissions), pre/post/midterm/final questions & exam settings.
 */
function lms_clone_subject_to_term(
    PDO $pdo,
    int $sourceSubjectId,
    int $targetYear,
    int $targetSemester,
    string $newSubjectName = '',
    string $newSubjectCode = '',
    bool $copyUnitsAndContent = true,
    bool $copyExercises = true,
    bool $copyExams = true
): int {
    lms_ensure_semester_columns($pdo);
    $src = $pdo->prepare("SELECT * FROM lms_subjects WHERE id=?");
    $src->execute([$sourceSubjectId]);
    $srcSubj = $src->fetch(PDO::FETCH_ASSOC);
    if (!$srcSubj) throw new Exception('ไม่พบวิชาต้นทาง');

    $finalName = trim($newSubjectName) !== '' ? trim($newSubjectName) : $srcSubj['subject_name'];
    $finalCode = trim($newSubjectCode) !== '' ? trim($newSubjectCode) : ($srcSubj['subject_code'] ?? null);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO lms_subjects (subject_name, subject_code, teacher_id, academic_year, semester)
            VALUES (?,?,?,?,?)
        ")->execute([
            $finalName,
            $finalCode ?: null,
            $srcSubj['teacher_id'] ?? null,
            $targetYear,
            $targetSemester,
        ]);
        $newSubjId = (int)$pdo->lastInsertId();

        // Copy classrooms
        $clStmt = $pdo->prepare("SELECT classroom FROM lms_subject_classrooms WHERE subject_id=?");
        $clStmt->execute([$sourceSubjectId]);
        $insCl = $pdo->prepare("INSERT IGNORE INTO lms_subject_classrooms (subject_id, classroom) VALUES (?,?)");
        foreach ($clStmt->fetchAll(PDO::FETCH_COLUMN) as $cl) {
            $insCl->execute([$newSubjId, $cl]);
        }

        // Copy subject settings (unlock_mode + midterm/final settings, clearing old open/close dates)
        $ssStmt = $pdo->prepare("SELECT * FROM lms_subject_settings WHERE subject_id=?");
        $ssStmt->execute([$sourceSubjectId]);
        $ss = $ssStmt->fetch(PDO::FETCH_ASSOC);
        if ($ss) {
            $pdo->prepare("
                INSERT INTO lms_subject_settings
                    (subject_id, unlock_mode, midterm_pass_score, midterm_max_attempts, midterm_show_answer, midterm_random_count,
                     final_pass_score, final_max_attempts, final_show_answer, final_random_count)
                VALUES (?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE unlock_mode=VALUES(unlock_mode)
            ")->execute([
                $newSubjId,
                $ss['unlock_mode'] ?? 'open_all',
                $ss['midterm_pass_score'] ?? 6,
                $ss['midterm_max_attempts'] ?? 1,
                $ss['midterm_show_answer'] ?? 0,
                $ss['midterm_random_count'] ?? 0,
                $ss['final_pass_score'] ?? 6,
                $ss['final_max_attempts'] ?? 1,
                $ss['final_show_answer'] ?? 0,
                $ss['final_random_count'] ?? 0,
            ]);
        } else {
            $pdo->prepare("INSERT IGNORE INTO lms_exam_settings (subject_id, pre_pass_score, post_pass_score, post_max_attempts) VALUES (?,6,6,3)")
                ->execute([$newSubjId]);
        }

        if ($copyUnitsAndContent) {
            $uStmt = $pdo->prepare("SELECT * FROM lms_units WHERE subject_id=? AND deleted_at IS NULL ORDER BY order_no, id");
            $uStmt->execute([$sourceSubjectId]);
            $units = $uStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($units as $u) {
                $oldUnitId = (int)$u['id'];
                $pdo->prepare("
                    INSERT INTO lms_units (subject_id, order_no, unit_number, unit_name, status)
                    VALUES (?,?,?,?,?)
                ")->execute([
                    $newSubjId,
                    $u['order_no'],
                    $u['unit_number'] ?? $u['order_no'],
                    $u['unit_name'],
                    $u['status'] ?? 'published',
                ]);
                $newUnitId = (int)$pdo->lastInsertId();

                // Copy topics & blocks
                $tStmt = $pdo->prepare("SELECT * FROM lms_topics WHERE unit_id=? AND deleted_at IS NULL ORDER BY order_no, id");
                $tStmt->execute([$oldUnitId]);
                foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
                    $oldTopicId = (int)$t['id'];
                    $pdo->prepare("
                        INSERT INTO lms_topics (unit_id, order_no, topic_name, status)
                        VALUES (?,?,?,?)
                    ")->execute([
                        $newUnitId,
                        $t['order_no'],
                        $t['topic_name'],
                        $t['status'] ?? 'published',
                    ]);
                    $newTopicId = (int)$pdo->lastInsertId();

                    $bStmt = $pdo->prepare("SELECT * FROM lms_topic_blocks WHERE topic_id=? ORDER BY order_no, id");
                    $bStmt->execute([$oldTopicId]);
                    $insB = $pdo->prepare("
                        INSERT INTO lms_topic_blocks (topic_id, order_no, block_type, title, body, media_url, media_path, original_name)
                        VALUES (?,?,?,?,?,?,?,?)
                    ");
                    foreach ($bStmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
                        $insB->execute([
                            $newTopicId,
                            $b['order_no'],
                            $b['block_type'],
                            $b['title'],
                            $b['body'],
                            $b['media_url'],
                            $b['media_path'],
                            $b['original_name'],
                        ]);
                    }
                }

                // Copy exercises (without due_date so old semester dates don't mark overdue)
                if ($copyExercises) {
                    $exStmt = $pdo->prepare("SELECT * FROM lms_unit_exercises WHERE unit_id=? AND deleted_at IS NULL ORDER BY id");
                    $exStmt->execute([$oldUnitId]);
                    $insEx = $pdo->prepare("
                        INSERT INTO lms_unit_exercises (unit_id, exercise_title, description, attachment_path, attachment_name, attachment_url, max_score, due_date, is_remedial, status)
                        VALUES (?,?,?,?,?,?,?,NULL,?,?)
                    ");
                    foreach ($exStmt->fetchAll(PDO::FETCH_ASSOC) as $ex) {
                        $insEx->execute([
                            $newUnitId,
                            $ex['exercise_title'],
                            $ex['description'],
                            $ex['attachment_path'] ?? null,
                            $ex['attachment_name'] ?? null,
                            $ex['attachment_url'] ?? null,
                            $ex['max_score'],
                            $ex['is_remedial'] ?? 0,
                            $ex['status'] ?? 'published',
                        ]);
                    }
                }

                // Copy unit exam settings & pre/post questions
                if ($copyExams) {
                    $esStmt = $pdo->prepare("SELECT * FROM lms_exam_settings WHERE unit_id=?");
                    $esStmt->execute([$oldUnitId]);
                    $es = $esStmt->fetch(PDO::FETCH_ASSOC);
                    if ($es) {
                        $pdo->prepare("
                            INSERT INTO lms_exam_settings (subject_id, unit_id, pre_pass_score, post_pass_score, post_max_attempts, post_show_answer, pre_random_count, post_random_count)
                            VALUES (?,?,?,?,?,?,?,?)
                        ")->execute([
                            $newSubjId,
                            $newUnitId,
                            $es['pre_pass_score'] ?? 6,
                            $es['post_pass_score'] ?? 6,
                            $es['post_max_attempts'] ?? 3,
                            $es['post_show_answer'] ?? 0,
                            $es['pre_random_count'] ?? 0,
                            $es['post_random_count'] ?? 0,
                        ]);
                    }

                    foreach (['lms_pre_questions', 'lms_post_questions'] as $qTbl) {
                        $qStmt = $pdo->prepare("SELECT * FROM `{$qTbl}` WHERE unit_id=? ORDER BY id");
                        $qStmt->execute([$oldUnitId]);
                        foreach ($qStmt->fetchAll(PDO::FETCH_ASSOC) as $q) {
                            $cols = array_keys($q);
                            $cols = array_values(array_diff($cols, ['id']));
                            $q['subject_id'] = $newSubjId;
                            $q['unit_id']    = $newUnitId;
                            $colList = '`' . implode('`,`', $cols) . '`';
                            $phList  = implode(',', array_fill(0, count($cols), '?'));
                            $vals    = array_map(fn($c) => $q[$c], $cols);
                            $pdo->prepare("INSERT INTO `{$qTbl}` ({$colList}) VALUES ({$phList})")->execute($vals);
                        }
                    }
                }
            }
        }

        // Copy subject-wide midterm & final questions
        if ($copyExams) {
            foreach (['lms_midterm_questions', 'lms_final_questions'] as $mqTbl) {
                $hasTbl = (bool)$pdo->query("SHOW TABLES LIKE '{$mqTbl}'")->fetchColumn();
                if (!$hasTbl) continue;
                $qStmt = $pdo->prepare("SELECT * FROM `{$mqTbl}` WHERE subject_id=? ORDER BY id");
                $qStmt->execute([$sourceSubjectId]);
                foreach ($qStmt->fetchAll(PDO::FETCH_ASSOC) as $q) {
                    $cols = array_values(array_diff(array_keys($q), ['id']));
                    $q['subject_id'] = $newSubjId;
                    $colList = '`' . implode('`,`', $cols) . '`';
                    $phList  = implode(',', array_fill(0, count($cols), '?'));
                    $vals    = array_map(fn($c) => $q[$c], $cols);
                    $pdo->prepare("INSERT INTO `{$mqTbl}` ({$colList}) VALUES ({$phList})")->execute($vals);
                }
            }
        }

        $pdo->commit();
        lms_log_activity($pdo, 'clone_subject', 'lms_subject', $newSubjId, ['source_id' => $sourceSubjectId], ['semester' => $targetSemester, 'year' => $targetYear]);
        return $newSubjId;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
