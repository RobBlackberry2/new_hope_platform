<?php
require_once __DIR__ . '/../models/Section.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../helpers/auth.php';

class AttendanceController
{
    public function listSections(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);

        $sectionModel = new Section();
        echo json_encode(['status' => 'success', 'data' => $sectionModel->listAll()]);
    }

    public function sectionsByGrade(): void
    {
        require_login();
        require_role(['ADMIN']);

        $grado = (int) ($_GET['grado'] ?? 0);
        if ($grado < 7 || $grado > 11) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Grado inválido']);
            return;
        }

        $sectionModel = new Section();
        echo json_encode(['status' => 'success', 'data' => $sectionModel->listByGrade($grado)]);
    }

    public function assignTeacher(): void
    {
        require_login();
        require_role(['ADMIN']);

        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $teacherRaw = $_POST['teacher_user_id'] ?? '';
        $teacherId = $teacherRaw === '' ? null : (int) $teacherRaw;

        if ($sectionId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta la sección']);
            return;
        }

        $sectionModel = new Section();
        $section = $sectionModel->getById($sectionId);
        if (!$section) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'La sección no existe']);
            return;
        }

        if ($teacherId !== null) {
            $userModel = new User();
            $teacher = $userModel->getById($teacherId);
            if (!$teacher || ($teacher['rol'] ?? '') !== 'DOCENTE') {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'El profesor guía debe ser un docente']);
                return;
            }
        }

        if (!$sectionModel->assignTeacher($sectionId, $teacherId)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo asignar el profesor guía']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }

    public function getSectionRoster(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);

        $sectionId = (int) ($_GET['section_id'] ?? 0);
        $attendanceDate = trim((string) ($_GET['attendance_date'] ?? date('Y-m-d')));

        if ($sectionId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta la sección']);
            return;
        }

        if (!$this->isValidDate($attendanceDate)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Fecha inválida']);
            return;
        }

        $sectionModel = new Section();
        $section = $sectionModel->getById($sectionId);
        if (!$section) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'La sección no existe']);
            return;
        }

        $this->assertSectionAccess($section);

        $students = $sectionModel->listStudentsBySectionCode((string) $section['codigo']);

        $data = [];
        foreach ($students as $student) {
            $studentId = (int) ($student['id'] ?? 0);
            $data[] = [
                'id' => $studentId,
                'nombre' => $student['nombre_completo'] ?? trim((string)(($student['nombre'] ?? '') . ' ' . ($student['apellidos'] ?? ''))),
                'cedula' => $student['cedula'] ?? '',
                'grado' => $student['grado'] ?? '',
                'seccion' => $student['seccion'] ?? '',
                'status' => 'PRESENTE',
                'is_justified' => 0,
            ];
        }

        echo json_encode([
            'status' => 'success',
            'section' => $section,
            'attendance_date' => $attendanceDate,
            'data' => $data,
        ]);
    }

    public function saveAttendance(): void
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);

        $sectionId = (int) ($_POST['section_id'] ?? 0);
        $attendanceDate = trim((string) ($_POST['attendance_date'] ?? ''));
        $rawItems = $_POST['items'] ?? '[]';
        if (!isset($_POST['items'])) {
            $rawInput = file_get_contents('php://input');
            parse_str($rawInput ?: '', $parsedBody);
            if (isset($parsedBody['section_id']) && $sectionId <= 0) {
                $sectionId = (int) $parsedBody['section_id'];
            }
            if (isset($parsedBody['attendance_date']) && $attendanceDate === '') {
                $attendanceDate = trim((string) $parsedBody['attendance_date']);
            }
            if (array_key_exists('items', $parsedBody)) {
                $rawItems = $parsedBody['items'];
            }
        }
        $items = is_string($rawItems) ? json_decode($rawItems, true) : $rawItems;

        if ($sectionId <= 0 || !$this->isValidDate($attendanceDate)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Datos de asistencia inválidos']);
            return;
        }

        if (!is_array($items)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Formato de asistencia inválido']);
            return;
        }

        $sectionModel = new Section();
        $section = $sectionModel->getById($sectionId);
        if (!$section) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'La sección no existe']);
            return;
        }

        $this->assertSectionAccess($section);

        $attendanceModel = new Attendance();
        $ok = $attendanceModel->saveForSectionDate($section, $attendanceDate, $items, (int) (current_user()['id'] ?? 0));
        if (!$ok) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la asistencia']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }

    public function history(): void
    {
        require_login();
        require_role(['ADMIN']);

        $sectionId = (int) ($_GET['section_id'] ?? 0);
        $attendanceDate = trim((string) ($_GET['attendance_date'] ?? ''));
        if ($sectionId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta la sección']);
            return;
        }

        if ($attendanceDate !== '' && !$this->isValidDate($attendanceDate)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Fecha inválida']);
            return;
        }

        $sectionModel = new Section();
        $section = $sectionModel->getById($sectionId);
        if (!$section) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'La sección no existe']);
            return;
        }

        $attendanceModel = new Attendance();
        echo json_encode([
            'status' => 'success',
            'section' => $section,
            'data' => $attendanceModel->listHistoryBySection($sectionId, $attendanceDate !== '' ? $attendanceDate : null),
        ]);
    }

    public function justify(): void
    {
        require_login();
        require_role(['ADMIN']);

        $attendanceId = (int) ($_POST['attendance_id'] ?? 0);
        if ($attendanceId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta el registro']);
            return;
        }

        $attendanceModel = new Attendance();
        if (!$attendanceModel->justify($attendanceId, (int) (current_user()['id'] ?? 0))) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo justificar el registro']);
            return;
        }

        echo json_encode(['status' => 'success']);
    }

    private function isValidDate(string $date): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    private function assertSectionAccess(array $section): void
    {
        $u = current_user();
        if (in_array(($u['rol'] ?? ''), ['ADMIN', 'DOCENTE'], true)) {
            return;
        }

        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'No tienes acceso a esta sección']);
        exit;
    }
}
