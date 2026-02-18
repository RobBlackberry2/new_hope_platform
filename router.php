<?php
header('Content-Type: application/json; charset=utf-8');


require_once __DIR__ . '/app/controllers/AuthController.php';
require_once __DIR__ . '/app/controllers/UsersController.php';
require_once __DIR__ . '/app/controllers/EnrollmentsController.php';
require_once __DIR__ . '/app/controllers/MessagesController.php';
require_once __DIR__ . '/app/controllers/ElearningController.php';

$action = $_GET['action'] ?? '';

$auth = new AuthController();
$users = new UsersController();
$mat = new EnrollmentsController();
$mess = new MessagesController();
$e = new ElearningController();

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
        case 'courses_create': $e->createCourse(); break;
        case 'courses_list': $e->listCourses(); break;
        case 'courses_delete': $e->deleteCourse(); break;
        
        case 'resources_upload': $e->uploadResource(); break;
        case 'resources_list': $e->listResources(); break;
        case 'resources_delete': $e->deleteResource(); break;

        case 'sections_create': $e->createSection(); break;
        case 'sections_list': $e->listSections(); break;
        case 'sections_delete': $e->deleteSection(); break;
        case 'sections_updateTipo': $e->updateSectionTipo(); break;

        case 'assignments_upsert': $e->upsertAssignment(); break;
        case 'assignments_getBySection': $e->getAssignmentBySection(); break;

        case 'submissions_upload': $e->uploadSubmission(); break;
        case 'submissions_getMine': $e->getMySubmission(); break;
        case 'submissions_listByAssignment': $e->listSubmissionsByAssignment(); break;

        case 'grades_set': $e->setGrade(); break;

        case 'groups_create': $e->createGroup(); break;
        case 'groups_list': $e->listGroups(); break;
        case 'groups_setMembers': $e->setGroupMembers(); break;

        case 'quizzes_upsert': $e->upsertQuiz(); break;
        case 'quizzes_getBySection': $e->getQuizBySection(); break;

        case 'quiz_questions_list': $e->listQuizQuestions(); break;
        case 'quiz_questions_upsert': $e->upsertQuizQuestion(); break;
        case 'quiz_questions_delete': $e->deleteQuizQuestion(); break;

        case 'quiz_options_upsert': $e->upsertQuizOption(); break;
        case 'quiz_options_delete': $e->deleteQuizOption(); break;

        case 'quiz_attempt_start': $e->startQuizAttempt(); break;
        case 'quiz_attempt_mine': $e->getMyQuizAttempt(); break;
        case 'quiz_attempt_submit': $e->submitQuizAttempt(); break;

        case 'quiz_attempts_list': $e->listQuizAttempts(); break;
        case 'quiz_attempt_grade_short': $e->gradeShort(); break;

        default:
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Ruta no encontrada']);
    }
} catch (Throwable $ex) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error interno', 'detail' => $ex->getMessage()]);
}


