<?php
header('Content-Type: application/json; charset=utf-8');


require_once __DIR__ . '/app/controllers/AuthController.php';
require_once __DIR__ . '/app/controllers/UsersController.php';
require_once __DIR__ . '/app/controllers/EnrollmentsController.php';
require_once __DIR__ . '/app/controllers/MessagesController.php';
require_once __DIR__ . '/app/controllers/ElearningController.php';
require_once __DIR__ . '/app/controllers/VirtualCampusController.php';
require_once __DIR__ . '/app/controllers/QuizController.php';

$action = $_GET['action'] ?? '';

$auth = new AuthController();
$users = new UsersController();
$mat = new EnrollmentsController();
$mess = new MessagesController();
$e = new ElearningController();
$vc = new VirtualCampusController();
$qz = new QuizController();

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
        case 'users_list_docentes': $users->listDocentes(); break;
        case 'users_create': $users->create(); break;
        case 'users_update': $users->update(); break;
        case 'users_setRole': $users->setRole(); break;
        case 'users_setEstado': $users->setEstado(); break;
        case 'users_delete': $users->delete(); break;

        // Administrativo - Matriculas (ADMIN)
        case 'students_create': $mat->createStudent(); break;
        case 'students_get': $mat->getStudent(); break;
        case 'students_list': $mat->listStudents(); break;
        case 'students_update': $mat->updateStudent(); break;
        case 'students_updateUserId': $mat->updateStudentUserId(); break;
        case 'students_delete': $mat->deleteStudent(); break;

        case 'enrollments_create': $mat->createEnrollment(); break;
        case 'enrollments_list': $mat->listEnrollments(); break;
        case 'enrollments_updateEstado': $mat->updateEnrollmentEstado(); break;
        case 'enrollments_delete': $mat->deleteEnrollment(); break;
        case 'enrollments_capacity': $mat->capacity(); break;

        // Mensajería
        case 'messages_inbox': $mess->inbox(); break;
        case 'messages_sent': $mess->sent(); break;
        case 'messages_send': $mess->send(); break;

        // E-Learning
        case 'course_get': $vc->getCourse(); break;
        case 'courses_create': $e->createCourse(); break;
        case 'courses_list': $e->listCourses(); break;
        case 'courses_delete': $e->deleteCourse(); break;
        
        case 'resources_upload': $vc->uploadResource(); break;
        case 'resources_list': $vc->listResources(); break;
        case 'resources_delete': $vc->deleteResource(); break;

        case 'sections_create': $vc->createSection(); break;
        case 'sections_list': $vc->listSections(); break;
        case 'sections_delete': $vc->deleteSection(); break;
        case 'sections_updateTipo': $vc->updateSectionTipo(); break;

        case 'assignments_upsert': $vc->upsertAssignment(); break;
        case 'assignments_getBySection': $vc->getAssignmentBySection(); break;

        case 'submissions_upload': $vc->uploadSubmission(); break;
        case 'submissions_getMine': $vc->getMySubmission(); break;
        case 'submissions_listByAssignment': $vc->listSubmissionsByAssignment(); break;

        case 'grades_set': $vc->setGrade(); break;

        case 'groups_create': $vc->createGroup(); break;
        case 'groups_list': $vc->listGroups(); break;
        case 'groups_setMembers': $vc->setGroupMembers(); break;

        // Quiz / Exámenes (separado)
        case 'quizzes_upsert': $qz->upsertQuiz(); break;
        case 'quizzes_getBySection': $qz->getQuizBySection(); break;

        case 'quiz_questions_list': $qz->listQuizQuestions(); break;
        case 'quiz_questions_upsert': $qz->upsertQuizQuestion(); break;
        case 'quiz_questions_delete': $qz->deleteQuizQuestion(); break;

        case 'quiz_options_upsert': $qz->upsertQuizOption(); break;
        case 'quiz_options_delete': $qz->deleteQuizOption(); break;

        case 'quiz_attempt_start': $qz->startQuizAttempt(); break;
        case 'quiz_attempt_mine': $qz->getMyQuizAttempt(); break;
        case 'quiz_attempt_submit': $qz->submitQuizAttempt(); break;

        case 'quiz_attempts_list': $qz->listQuizAttempts(); break;
        case 'quiz_attempt_grade_short': $qz->gradeShort(); break;

        default:
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Ruta no encontrada']);
    }
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno', 'detail' => $ex->getMessage()]);
}


