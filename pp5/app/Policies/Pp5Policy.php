<?php
namespace PP5\App\Policies;

use PDO;
use Throwable;

/**
 * Pp5Policy — ระบบตรวจสอบสิทธิ์และบทบาทผู้ใช้งานระบบ ปพ.5 ออนไลน์ (Multi-Role & Policy Engine)
 *
 * รองรับผู้ใช้งาน 1 คนมีได้หลายบทบาทพร้อมกัน เช่น:
 * - เป็นทั้งครูผู้สอน (teacher), ครูที่ปรึกษา (advisor), และหัวหน้ากลุ่มสาระ (head)
 * - ตรวจสิทธิ์ทุก Request ด้วยเมธอด Policy (ไม่ใช่แค่ซ่อนปุ่มในหน้าเว็บ)
 */
class Pp5Policy
{
    private PDO $pdo;
    private int $userId;
    private string $primaryRole;
    private array $userRoles = [];
    private array $headedDepartmentIds = [];
    private array $advisedClassroomNames = [];

    public function __construct(PDO $pdo, ?int $userId = null)
    {
        $this->pdo         = $pdo;
        $this->userId      = $userId ?? (int)($_SESSION['user_id'] ?? 0);
        $this->primaryRole = trim((string)($_SESSION['llw_role'] ?? ''));

        $sessionRoles = $_SESSION['llw_roles'] ?? [];
        if (is_array($sessionRoles) && !empty($sessionRoles)) {
            $this->userRoles = $sessionRoles;
        } elseif ($this->primaryRole !== '') {
            $this->userRoles = [$this->primaryRole];
        }

        $this->loadExtendedContext();
    }

    /**
     * โหลดข้อมูลบทบาทเพิ่มเติมจากฐานข้อมูล (หัวหน้ากลุ่มสาระ, ครูที่ปรึกษา, และ llw_user_roles)
     */
    private function loadExtendedContext(): void
    {
        if ($this->userId <= 0) {
            return;
        }

        // 1. ดึงจาก llw_user_roles (ถ้ายังไม่มีใน session)
        try {
            $stmt = $this->pdo->prepare("SELECT `role` FROM `llw_user_roles` WHERE `user_id` = ?");
            $stmt->execute([$this->userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $r) {
                $r = trim((string)$r);
                if ($r !== '' && !in_array($r, $this->userRoles, true)) {
                    $this->userRoles[] = $r;
                }
            }
        } catch (Throwable $e) {}

        // 2. ตรวจว่าเป็นหัวหน้ากลุ่มสาระใดบ้าง (pp5_departments.head_user_id)
        try {
            $stmt = $this->pdo->prepare("SELECT `id` FROM `pp5_departments` WHERE `head_user_id` = ?");
            $stmt->execute([$this->userId]);
            $this->headedDepartmentIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {}

        // 3. ตรวจว่าเป็นครูที่ปรึกษาห้องใดบ้าง (pp5_classrooms และ llw_class_advisors)
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT `classroom_name`
                FROM `pp5_classrooms`
                WHERE `advisor_user_id` = ? OR `co_advisor_user_id` = ?
            ");
            $stmt->execute([$this->userId, $this->userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $rm) {
                $this->advisedClassroomNames[] = trim((string)$rm);
            }

            $stmt2 = $this->pdo->prepare("SELECT DISTINCT `classroom` FROM `llw_class_advisors` WHERE `user_id` = ?");
            $stmt2->execute([$this->userId]);
            foreach ($stmt2->fetchAll(PDO::FETCH_COLUMN) as $rm) {
                $rm = trim((string)$rm);
                if ($rm !== '' && !in_array($rm, $this->advisedClassroomNames, true)) {
                    $this->advisedClassroomNames[] = $rm;
                }
            }
        } catch (Throwable $e) {}
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * 1. admin (งานทะเบียนและวัดผล): ตั้งค่าทั้งระบบ นำเข้าข้อมูล จัดการผู้ใช้ ปลดล็อก ปพ.5
     */
    public function isAdmin(): bool
    {
        return !empty(array_intersect($this->userRoles, ['super_admin', 'academic_admin', 'admin']));
    }

    /**
     * 2. teacher (ครูผู้สอน): บุคลากรครูทุกคนที่สอนรายวิชา
     */
    public function isTeacher(): bool
    {
        if ($this->isStudent()) {
            return false;
        }
        return !empty(array_intersect($this->userRoles, ['att_teacher', 'super_admin', 'academic_admin', 'head', 'deputy_director', 'director', 'wfh_staff']));
    }

    /**
     * 3. advisor (ครูที่ปรึกษา): มีห้องเรียนที่ดูแล
     */
    public function isAdvisor(): bool
    {
        return !empty($this->advisedClassroomNames) || in_array('advisor', $this->userRoles, true);
    }

    public function getAdvisedClassrooms(): array
    {
        return $this->advisedClassroomNames;
    }

    /**
     * 4. head (หัวหน้ากลุ่มสาระ): เป็นหัวหน้ากลุ่มสาระอย่างน้อย 1 กลุ่มสาระ
     */
    public function isDepartmentHead(?int $departmentId = null): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        if ($departmentId !== null) {
            return in_array($departmentId, $this->headedDepartmentIds, true);
        }
        return !empty($this->headedDepartmentIds) || in_array('department_head', $this->userRoles, true);
    }

    public function getHeadedDepartmentIds(): array
    {
        return $this->headedDepartmentIds;
    }

    /**
     * 5. deputy (รองผู้อำนวยการฝ่ายวิชาการ)
     */
    public function isDeputy(): bool
    {
        return !empty(array_intersect($this->userRoles, ['deputy_director', 'deputy', 'super_admin']));
    }

    /**
     * 6. director (ผู้อำนวยการ)
     */
    public function isDirector(): bool
    {
        return !empty(array_intersect($this->userRoles, ['director', 'super_admin']));
    }

    /**
     * 7. student (นักเรียน)
     */
    public function isStudent(): bool
    {
        return $this->primaryRole === 'student';
    }

    /**
     * สิทธิ์ดูเลขบัตรประชาชนเต็ม 13 หลักตาม PDPA (เฉพาะงานทะเบียนและวัดผล / ผู้บริหาร / ครูที่ปรึกษาของห้องนั้น)
     */
    public function canViewFullCitizenId(?string $classroomName = null): bool
    {
        if ($this->isAdmin() || $this->isDirector() || $this->isDeputy()) {
            return true;
        }
        if ($classroomName !== null && in_array(trim($classroomName), $this->advisedClassroomNames, true)) {
            return true;
        }
        return false;
    }

    /**
     * ตรวจสอบว่าผู้ใช้มีสิทธิ์เข้าดูรายวิชา ปพ.5 (Course) หรือไม่
     */
    public function canViewCourse(array $course): bool
    {
        if ($this->isAdmin() || $this->isDeputy() || $this->isDirector()) {
            return true;
        }

        // ครูผู้สอนหลัก
        if ((int)($course['teacher_user_id'] ?? 0) === $this->userId) {
            return true;
        }

        // หัวหน้ากลุ่มสาระของวิชานี้
        $deptId = (int)($course['department_id'] ?? 0);
        if ($deptId > 0 && in_array($deptId, $this->headedDepartmentIds, true)) {
            return true;
        }

        // ครูที่ปรึกษาของห้องที่เรียนวิชานี้
        $clsName = trim((string)($course['classroom_name'] ?? ''));
        if ($clsName !== '' && in_array($clsName, $this->advisedClassroomNames, true)) {
            return true;
        }

        // ครูสอนร่วม (pp5_course_teachers)
        $courseId = (int)($course['id'] ?? 0);
        if ($courseId > 0) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM `pp5_course_teachers` WHERE `course_id` = ? AND `user_id` = ? LIMIT 1");
            $stmt->execute([$courseId, $this->userId]);
            if ((bool)$stmt->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    /**
     * ตรวจสอบว่าครูแก้ไขข้อมูลคะแนน/เช็คชื่อ/คุณลักษณะของรายวิชา ปพ.5 นี้ได้หรือไม่
     * กฎเหล็ก:
     * - ต้องเป็นครูผู้สอนประจำวิชา (หรือครูสอนร่วม หรือ Admin)
     * - สถานะของ ปพ.5 ต้องเป็น `draft` เท่านั้น (เมื่อเป็น submitted ขึ้นไป ครูแก้ไขข้อมูลไม่ได้)
     */
    public function canEditCourse(array $course): bool
    {
        $status = (string)($course['status'] ?? 'draft');
        if ($status !== 'draft') {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if ((int)($course['teacher_user_id'] ?? 0) === $this->userId) {
            return true;
        }

        $courseId = (int)($course['id'] ?? 0);
        if ($courseId > 0) {
            $stmt = $this->pdo->prepare("SELECT 1 FROM `pp5_course_teachers` WHERE `course_id` = ? AND `user_id` = ? LIMIT 1");
            $stmt->execute([$courseId, $this->userId]);
            return (bool)$stmt->fetchColumn();
        }

        return false;
    }

    /**
     * สิทธิ์กดส่งตรวจ ปพ.5 (เฉพาะครูผู้สอนวิชา หรือ Admin และสถานะต้องเป็น draft)
     */
    public function canSubmitCourse(array $course): bool
    {
        $status = (string)($course['status'] ?? 'draft');
        if ($status !== 'draft') {
            return false;
        }
        if ($this->isAdmin()) {
            return true;
        }
        return (int)($course['teacher_user_id'] ?? 0) === $this->userId;
    }

    /**
     * สิทธิ์การอนุมัติ ปพ.5 ตามลำดับขั้น
     * - 'head': หัวหน้ากลุ่มสาระ (สถานะ submitted)
     * - 'deputy': รองฯ วิชาการ (สถานะ head_approved หรือ submitted สำหรับ deputy/admin)
     * - 'director': ผู้อำนวยการ (สถานะ deputy_approved หรือ submitted/head_approved สำหรับ director/admin)
     */
    public function canApproveCourse(array $course, ?string $step = null): bool
    {
        $status = (string)($course['status'] ?? 'draft');
        $deptId = (int)($course['department_id'] ?? 0);

        if ($step === 'head' || $step === null) {
            if ($status === 'submitted' && ($this->isAdmin() || $this->isDepartmentHead($deptId))) {
                return true;
            }
        }
        if ($step === 'deputy' || $step === null) {
            if (in_array($status, ['head_approved', 'submitted'], true) && ($this->isAdmin() || $this->isDeputy())) {
                return true;
            }
        }
        if ($step === 'director' || $step === null) {
            if (in_array($status, ['deputy_approved', 'head_approved', 'submitted'], true) && ($this->isAdmin() || $this->isDirector())) {
                return true;
            }
        }

        return false;
    }

    /**
     * สิทธิ์ส่งกลับให้ครูแก้ไข (Reject with Reason)
     */
    public function canRejectCourse(array $course): bool
    {
        $status = (string)($course['status'] ?? 'draft');
        if (in_array($status, ['draft', 'approved', 'locked'], true)) {
            return false;
        }
        $deptId = (int)($course['department_id'] ?? 0);
        return $this->isAdmin() || $this->isDirector() || $this->isDeputy() || $this->isDepartmentHead($deptId);
    }

    /**
     * ตรวจสอบสิทธิ์ปลดล็อก ปพ.5 ที่สถานะ locked หรืออนุมัติไปแล้ว (เฉพาะ Admin งานทะเบียนและวัดผล)
     */
    public function canUnlockCourse(): bool
    {
        return $this->isAdmin();
    }

    /**
     * บังคับสิทธิ์ระดับ Admin (ถ้าไม่ผ่านจะส่ง HTTP 403 ทันที)
     */
    public function authorizeAdmin(bool $isApi = false): void
    {
        if (!$this->isAdmin()) {
            if ($isApi) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'คุณไม่มีสิทธิ์ดำเนินการในส่วนของงานทะเบียนและวัดผล'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            http_response_code(403);
            die('คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (เฉพาะงานทะเบียนและวัดผล)');
        }
    }

    /**
     * คืนค่ารายการ Badge บทบาททั้งหมดของผู้ใช้ปัจจุบันสำหรับแสดงบนหน้าจอ
     */
    public function getRoleLabels(): array
    {
        $badges = [];
        if ($this->isAdmin()) {
            $badges[] = ['label' => 'งานทะเบียนและวัดผล (Admin)', 'icon' => 'bi-shield-lock-fill', 'class' => 'bg-purple-100 text-purple-700 border border-purple-200'];
        }
        if ($this->isDirector() && !in_array('director', array_column($badges, 'key'), true)) {
            if (in_array('director', $this->userRoles, true)) {
                $badges[] = ['label' => 'ผู้อำนวยการ', 'icon' => 'bi-award-fill', 'class' => 'bg-amber-100 text-amber-800 border border-amber-200'];
            }
        }
        if (in_array('deputy_director', $this->userRoles, true) || in_array('deputy', $this->userRoles, true)) {
            $badges[] = ['label' => 'รองฯ ฝ่ายวิชาการ', 'icon' => 'bi-person-check-fill', 'class' => 'bg-indigo-100 text-indigo-700 border border-indigo-200'];
        }
        if (!empty($this->headedDepartmentIds)) {
            $badges[] = ['label' => 'หัวหน้ากลุ่มสาระ', 'icon' => 'bi-diagram-3-fill', 'class' => 'bg-teal-100 text-teal-700 border border-teal-200'];
        }
        if (!empty($this->advisedClassroomNames)) {
            $rooms = implode(', ', $this->advisedClassroomNames);
            $badges[] = ['label' => "ครูที่ปรึกษา ({$rooms})", 'icon' => 'bi-people-fill', 'class' => 'bg-emerald-100 text-emerald-700 border border-emerald-200'];
        }
        if ($this->isTeacher()) {
            $badges[] = ['label' => 'ครูผู้สอน', 'icon' => 'bi-easel2-fill', 'class' => 'bg-blue-100 text-blue-700 border border-blue-200'];
        }
        if ($this->isStudent()) {
            $badges[] = ['label' => 'นักเรียน', 'icon' => 'bi-mortarboard-fill', 'class' => 'bg-sky-100 text-sky-700 border border-sky-200'];
        }
        return $badges;
    }

    /**
     * นับจำนวนแจ้งเตือนที่ยังไม่ได้อ่านของผู้ใช้ปัจจุบัน
     */
    public function getUnreadNotificationCount(): int
    {
        if ($this->userId <= 0) {
            return 0;
        }
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM `pp5_notifications` WHERE `user_id` = ? AND `is_read` = 0");
            $stmt->execute([$this->userId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}
