<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/app/controllers/AuthController.php';
require_once __DIR__ . '/app/controllers/UsersController.php';
require_once __DIR__ . '/app/controllers/EnrollmentsController.php';
require_once __DIR__ . '/app/controllers/MessagesController.php';
require_once __DIR__ . '/app/controllers/ElearningController.php';
require_once __DIR__ . '/app/controllers/GradesController.php';
require_once __DIR__ . '/app/controllers/AttendanceController.php';
require_once __DIR__ . '/app/controllers/ReportsController.php';

$action = $_GET['action'] ?? '';

$auth = new AuthController();
$users = new UsersController();
$mat = new EnrollmentsController();
$mess = new MessagesController();
$e = new ElearningController();
$grades = new GradesController();
$att = new AttendanceController();
$reports = new ReportsController();

try {
    switch ($action) {
        // Auth
        case 'login': $auth->login(); break;
        case 'register': $auth->register(); break;
        case 'me': $auth->me(); break;
        case 'logout': $auth->logout(); break;

        // Gestión de usuarios (ADMIN)
        case 'users_list': $users->list(); break;
        case 'users_list_for_students': $users->listForStudents(); break;
        case 'users_create': $users->create(); break;
        case 'users_update': $users->update(); break;
        case 'users_setRole': $users->setRole(); break;
        case 'users_setEstado': $users->setEstado(); break;
        case 'users_delete': $users->delete(); break;

        // Administrativo - Matriculas (ADMIN)
        case 'students_create': $mat->createStudent(); break;
        case 'students_list': $mat->listStudents(); break;
        case 'students_update': $mat->updateStudent(); break;
        case 'students_updateUserId': $mat->updateStudentUserId(); break;
        case 'students_delete': $mat->deleteStudent(); break;

        case 'enrollments_create': $mat->createEnrollment(); break;
        case 'enrollments_list': $mat->listEnrollments(); break;
        case 'enrollments_updateEstado': $mat->updateEnrollmentEstado(); break;
        case 'enrollments_delete': $mat->deleteEnrollment(); break;

        // Mensajería
        case 'messages_inbox': $mess->inbox(); break;
        case 'messages_sent': $mess->sent(); break;
        case 'messages_send': $mess->send(); break;

        // E-Learning
        case 'courses_create': $e->createCourse(); break;
        case 'courses_list': $e->listCourses(); break;
        case 'sections_create': $e->createSection(); break;
        case 'sections_list': $e->listSections(); break;
        case 'resources_upload': $e->uploadResource(); break;
        case 'resources_list': $e->listResources(); break;
        case 'resources_delete': $e->deleteResource(); break;

        // Gestión de Calificaciones
        case 'grades_create': $grades->createGrade(); break;
        case 'grades_list': $grades->listGrades(); break;
        case 'grades_update': $grades->updateGrade(); break;
        case 'grades_delete': $grades->deleteGrade(); break;
        case 'grades_student': $grades->getStudentGrades(); break;

        // Gestión de Asistencia
        case 'attendance_create': $att->createAttendance(); break;
        case 'attendance_list': $att->listAttendance(); break;
        case 'attendance_update': $att->updateAttendance(); break;
        case 'attendance_student': $att->getStudentAttendance(); break;

        // Reportes - Administrador (REF-001 a REF-005)
        case 'reports_create': $reports->createReport(); break;
        case 'reports_list': $reports->listReports(); break;
        case 'reports_update': $reports->updateReport(); break;
        case 'reports_delete': $reports->deleteReport(); break;
        case 'reports_archive': $reports->archiveReport(); break;
        case 'reports_restore': $reports->restoreReport(); break;
        case 'reports_institutional': $reports->generateInstitutionalReport(); break;
        case 'reports_export': $reports->exportReport(); break;

        // Reportes - Docente (REF-006 a REF-008)
        case 'reports_group': $reports->generateGroupReport(); break;
        case 'reports_comparative': $reports->generateComparativeReport(); break;
        case 'reports_group_export': $reports->exportGroupReport(); break;
        case 'observations_add': $reports->addObservation(); break;
        case 'observations_update': $reports->updateObservation(); break;
        case 'observations_delete': $reports->deleteObservation(); break;

        // Reportes - Padre (REF-009 a REF-011)
        case 'reports_student_view': $reports->viewStudentReport(); break;
        case 'reports_student_download': $reports->downloadStudentReport(); break;
        case 'reports_attendance_view': $reports->viewAttendanceReport(); break;
        case 'reports_attendance_export': $reports->exportAttendanceReport(); break;

        // Reportes - Estudiante (REF-012 a REF-014)
        case 'reports_my_view': $reports->viewMyReport(); break;
        case 'reports_my_download': $reports->downloadMyReport(); break;
        case 'reports_my_compare': $reports->compareMyReports(); break;
        case 'reports_my_comment': $reports->sendReportComment(); break;

        default:
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Ruta no encontrada']);
    }
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno', 'detail' => $ex->getMessage()]);
}
