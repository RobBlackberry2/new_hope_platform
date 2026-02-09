<?php
require_once __DIR__ . '/../models/Report.php';
require_once __DIR__ . '/../models/ReportObservation.php';
require_once __DIR__ . '/../models/ReportNotification.php';
require_once __DIR__ . '/../models/ParentStudentRelation.php';
require_once __DIR__ . '/../models/Student.php';
require_once __DIR__ . '/../models/Message.php';
require_once __DIR__ . '/../helpers/auth.php';

class ReportsController {
    
    // ============================================
    // MÉTODOS PARA ADMINISTRADORES (REF-001 a REF-005)
    // ============================================
    
    // REF-001: Crear nuevo reporte
    public function createReport(): void {
        require_login();
        require_role(['ADMIN']);
        
        $tipo = $_POST['tipo'] ?? '';
        $titulo = $_POST['titulo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? null;
        $periodo_inicio = $_POST['periodo_inicio'] ?? null;
        $periodo_fin = $_POST['periodo_fin'] ?? null;
        $u = current_user();

        if (!$tipo || !$titulo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Debe completar todos los campos obligatorios']);
            return;
        }

        $model = new Report();
        $id = $model->create([
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'periodo_inicio' => $periodo_inicio,
            'periodo_fin' => $periodo_fin,
            'created_by' => $u['id']
        ]);

        if ($id) {
            echo json_encode(['status' => 'success', 'message' => 'Reporte creado exitosamente', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error de conexión. No se pudo crear el reporte']);
        }
    }

    // REF-002: Listar y filtrar reportes
    public function listReports(): void {
        require_login();
        require_role(['ADMIN']);
        
        $filters = [];
        if (!empty($_GET['tipo'])) $filters['tipo'] = $_GET['tipo'];
        if (!empty($_GET['estado'])) $filters['estado'] = $_GET['estado'];
        if (!empty($_GET['periodo_inicio'])) $filters['periodo_inicio'] = $_GET['periodo_inicio'];
        if (!empty($_GET['periodo_fin'])) $filters['periodo_fin'] = $_GET['periodo_fin'];
        
        $limit = (int)($_GET['limit'] ?? 200);

        $model = new Report();
        $data = $model->list($filters, $limit);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    // REF-003: Actualizar reporte
    public function updateReport(): void {
        require_login();
        require_role(['ADMIN']);
        
        $id = (int)($_POST['id'] ?? 0);
        $titulo = $_POST['titulo'] ?? '';
        $descripcion = $_POST['descripcion'] ?? null;
        $periodo_inicio = $_POST['periodo_inicio'] ?? null;
        $periodo_fin = $_POST['periodo_fin'] ?? null;

        if (!$id || !$titulo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Debe completar todos los campos obligatorios']);
            return;
        }

        $model = new Report();
        if ($model->update($id, [
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'periodo_inicio' => $periodo_inicio,
            'periodo_fin' => $periodo_fin
        ])) {
            echo json_encode(['status' => 'success', 'message' => 'Reporte actualizado exitosamente']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar el reporte']);
        }
    }

    // REF-004: Eliminar reporte
    public function deleteReport(): void {
        require_login();
        require_role(['ADMIN']);
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $model = new Report();
        if ($model->delete($id)) {
            echo json_encode(['status' => 'success', 'message' => 'Reporte eliminado exitosamente']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No tiene permisos para eliminar reportes']);
        }
    }

    // REF-004: Archivar reporte
    public function archiveReport(): void {
        require_login();
        require_role(['ADMIN']);
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $model = new Report();
        if ($model->archive($id)) {
            echo json_encode(['status' => 'success', 'message' => 'Reporte archivado exitosamente']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo archivar el reporte']);
        }
    }

    // REF-004: Restaurar reporte archivado
    public function restoreReport(): void {
        require_login();
        require_role(['ADMIN']);
        
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        $model = new Report();
        if ($model->restore($id)) {
            echo json_encode(['status' => 'success', 'message' => 'Reporte restaurado exitosamente']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo restaurar el reporte']);
        }
    }

    // REF-005: Generar reporte institucional
    public function generateInstitutionalReport(): void {
        require_login();
        require_role(['ADMIN']);
        
        $tipo = $_GET['tipo'] ?? 'general';
        $periodo = $_GET['periodo'] ?? '';

        $model = new Report();
        $data = $model->generateInstitutionalReport(['tipo' => $tipo, 'periodo' => $periodo]);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    // REF-002, REF-005: Exportar reporte
    public function exportReport(): void {
        require_login();
        require_role(['ADMIN']);
        
        $id = (int)($_GET['id'] ?? 0);
        $format = $_GET['format'] ?? 'csv'; // csv o pdf

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id del reporte']);
            return;
        }

        $model = new Report();
        $report = $model->get($id);
        
        if (!$report) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Reporte no encontrado']);
            return;
        }

        if ($format === 'csv') {
            $this->exportToCSV($report);
        } else {
            // TODO: Implementar exportación a PDF con TCPDF
            echo json_encode(['status' => 'error', 'message' => 'Formato PDF no implementado aún']);
        }
    }

    // ============================================
    // MÉTODOS PARA DOCENTES (REF-006 a REF-008)
    // ============================================

    // REF-006: Generar reporte de grupo
    public function generateGroupReport(): void {
        require_login();
        require_role(['DOCENTE', 'ADMIN']);
        
        $course_id = (int)($_GET['course_id'] ?? 0);
        $periodo = $_GET['periodo'] ?? '';
        $u = current_user();

        if (!$course_id || !$periodo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar un grupo válido']);
            return;
        }

        // Verificar que el docente tenga acceso al curso
        if ($u['rol'] === 'DOCENTE') {
            $stmt = (Database::connect())->prepare('SELECT id FROM courses WHERE id = ? AND docente_user_id = ?');
            $stmt->bind_param('ii', $course_id, $u['id']);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No tiene permisos para realizar esta acción']);
                return;
            }
        }

        $model = new Report();
        $data = $model->generateGroupReport($course_id, $periodo);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    // REF-007: Generar reporte comparativo
    public function generateComparativeReport(): void {
        require_login();
        require_role(['DOCENTE', 'ADMIN']);
        
        $tipo = $_GET['tipo'] ?? 'grupos';
        $periodo = $_GET['periodo'] ?? '';
        $ids = $_GET['ids'] ?? '';

        if (!$ids) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar al menos un grupo o estudiante']);
            return;
        }

        $ids_array = explode(',', $ids);
        $params = [
            'tipo' => $tipo,
            'periodo' => $periodo
        ];

        if ($tipo === 'grupos') {
            $params['course_ids'] = $ids_array;
        } else {
            $params['student_ids'] = $ids_array;
        }

        $model = new Report();
        $data = $model->generateComparativeReport($params);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    // REF-006: Exportar reporte de grupo
    public function exportGroupReport(): void {
        require_login();
        require_role(['DOCENTE', 'ADMIN']);
        
        $course_id = (int)($_GET['course_id'] ?? 0);
        $periodo = $_GET['periodo'] ?? '';
        $format = $_GET['format'] ?? 'csv';

        if (!$course_id || !$periodo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros']);
            return;
        }

        $model = new Report();
        $data = $model->generateGroupReport($course_id, $periodo);
        
        if ($format === 'csv') {
            $this->exportArrayToCSV($data, "grupo_{$course_id}_{$periodo}.csv");
        }
    }

    // REF-008: Agregar observación
    public function addObservation(): void {
        require_login();
        require_role(['DOCENTE', 'ADMIN']);
        
        $student_id = (int)($_POST['student_id'] ?? 0);
        $observacion = $_POST['observacion'] ?? '';
        $report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : null;
        $u = current_user();

        if (!$student_id || !$observacion) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos requeridos']);
            return;
        }

        $model = new ReportObservation();
        $id = $model->create([
            'report_id' => $report_id,
            'student_id' => $student_id,
            'docente_user_id' => $u['id'],
            'observacion' => $observacion
        ]);

        if ($id) {
            echo json_encode(['status' => 'success', 'message' => 'Observación guardada exitosamente', 'id' => $id]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo guardar la observación']);
        }
    }

    // REF-008: Actualizar observación
    public function updateObservation(): void {
        require_login();
        require_role(['DOCENTE', 'ADMIN']);
        
        $id = (int)($_POST['id'] ?? 0);
        $observacion = $_POST['observacion'] ?? '';
        $u = current_user();

        if (!$id || !$observacion) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan datos']);
            return;
        }

        // Verificar que la observación pertenece al docente (si no es admin)
        if ($u['rol'] === 'DOCENTE') {
            $model = new ReportObservation();
            $obs = $model->get($id);
            if (!$obs || $obs['docente_user_id'] != $u['id']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No tiene permisos para realizar esta acción']);
                return;
            }
        }

        $model = new ReportObservation();
        if ($model->update($id, $observacion)) {
            echo json_encode(['status' => 'success', 'message' => 'Observación actualizada exitosamente']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar']);
        }
    }

    // REF-008: Eliminar observación
    public function deleteObservation(): void {
        require_login();
        require_role(['DOCENTE', 'ADMIN']);
        
        $id = (int)($_POST['id'] ?? 0);
        $u = current_user();

        if (!$id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta id']);
            return;
        }

        // Verificar permisos
        if ($u['rol'] === 'DOCENTE') {
            $model = new ReportObservation();
            $obs = $model->get($id);
            if (!$obs || $obs['docente_user_id'] != $u['id']) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'No tiene permisos para realizar esta acción']);
                return;
            }
        }

        $model = new ReportObservation();
        if ($model->delete($id)) {
            echo json_encode(['status' => 'success', 'message' => 'Observación eliminada exitosamente']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
        }
    }

    // ============================================
    // MÉTODOS PARA PADRES (REF-009 a REF-011)
    // ============================================

    // REF-009: Consultar reporte académico del estudiante
    public function viewStudentReport(): void {
        require_login();
        require_role(['PADRE', 'ADMIN']);
        
        $student_id = (int)($_GET['student_id'] ?? 0);
        $periodo = $_GET['periodo'] ?? '';
        $u = current_user();

        if (!$student_id || !$periodo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros']);
            return;
        }

        // Verificar acceso del padre
        if ($u['rol'] === 'PADRE') {
            $relationModel = new ParentStudentRelation();
            if (!$relationModel->hasAccess($u['id'], $student_id)) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
                return;
            }
        }

        $model = new Report();
        $data = $model->generateAcademicReport($student_id, $periodo);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    // REF-009: Descargar reporte del estudiante
    public function downloadStudentReport(): void {
        require_login();
        require_role(['PADRE', 'ADMIN']);
        
        $student_id = (int)($_GET['student_id'] ?? 0);
        $periodo = $_GET['periodo'] ?? '';
        $u = current_user();

        if (!$student_id || !$periodo) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros']);
            return;
        }

        // Verificar acceso
        if ($u['rol'] === 'PADRE') {
            $relationModel = new ParentStudentRelation();
            if (!$relationModel->hasAccess($u['id'], $student_id)) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
                return;
            }
        }

        $model = new Report();
        $data = $model->generateAcademicReport($student_id, $periodo);
        
        $this->exportArrayToCSV($data, "reporte_estudiante_{$student_id}_{$periodo}.csv");
    }

    // REF-011: Consultar asistencia
    public function viewAttendanceReport(): void {
        require_login();
        require_role(['PADRE', 'ADMIN']);
        
        $student_id = (int)($_GET['student_id'] ?? 0);
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        $u = current_user();

        if (!$student_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No hay datos disponibles']);
            return;
        }

        // Verificar acceso
        if ($u['rol'] === 'PADRE') {
            $relationModel = new ParentStudentRelation();
            if (!$relationModel->hasAccess($u['id'], $student_id)) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
                return;
            }
        }

        $attendanceModel = new Attendance();
        $data = $attendanceModel->getByStudent($student_id, $fecha_inicio, $fecha_fin);
        $summary = $attendanceModel->getSummary($student_id, $fecha_inicio, $fecha_fin);
        
        echo json_encode(['status' => 'success', 'data' => ['records' => $data, 'summary' => $summary]]);
    }

    // REF-011: Exportar asistencia
    public function exportAttendanceReport(): void {
        require_login();
        require_role(['PADRE', 'ADMIN']);
        
        $student_id = (int)($_GET['student_id'] ?? 0);
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        $u = current_user();

        if (!$student_id) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Faltan parámetros']);
            return;
        }

        // Verificar acceso
        if ($u['rol'] === 'PADRE') {
            $relationModel = new ParentStudentRelation();
            if (!$relationModel->hasAccess($u['id'], $student_id)) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Acceso denegado']);
                return;
            }
        }

        $attendanceModel = new Attendance();
        $data = $attendanceModel->getByStudent($student_id, $fecha_inicio, $fecha_fin);
        
        $this->exportArrayToCSV(['attendance' => $data], "asistencia_{$student_id}.csv");
    }

    // ============================================
    // MÉTODOS PARA ESTUDIANTES (REF-012 a REF-014)
    // ============================================

    // REF-012: Visualizar mis notas y asistencia
    public function viewMyReport(): void {
        require_login();
        require_role(['ESTUDIANTE', 'ADMIN']);
        
        $periodo = $_GET['periodo'] ?? '';
        $u = current_user();

        // Obtener student_id del usuario actual
        $studentModel = new Student();
        $student = $studentModel->getByUserId($u['id']);
        
        if (!$student) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Perfil de estudiante no encontrado']);
            return;
        }

        $model = new Report();
        $data = $model->generateAcademicReport($student['id'], $periodo);
        
        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    // REF-012: Descargar mi reporte
    public function downloadMyReport(): void {
        require_login();
        require_role(['ESTUDIANTE', 'ADMIN']);
        
        $periodo = $_GET['periodo'] ?? '';
        $u = current_user();

        $studentModel = new Student();
        $student = $studentModel->getByUserId($u['id']);
        
        if (!$student) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Perfil no encontrado']);
            return;
        }

        $model = new Report();
        $data = $model->generateAcademicReport($student['id'], $periodo);
        
        $this->exportArrayToCSV($data, "mi_reporte_{$periodo}.csv");
    }

    // REF-013: Comparar mis reportes entre periodos
    public function compareMyReports(): void {
        require_login();
        require_role(['ESTUDIANTE', 'ADMIN']);
        
        $periodo1 = $_GET['periodo1'] ?? '';
        $periodo2 = $_GET['periodo2'] ?? '';
        $u = current_user();

        if (!$periodo1 || !$periodo2) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Debe seleccionar periodos válidos']);
            return;
        }

        $studentModel = new Student();
        $student = $studentModel->getByUserId($u['id']);
        
        if (!$student) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Perfil no encontrado']);
            return;
        }

        $model = new Report();
        $data1 = $model->generateAcademicReport($student['id'], $periodo1);
        $data2 = $model->generateAcademicReport($student['id'], $periodo2);
        
        echo json_encode(['status' => 'success', 'data' => [
            'periodo1' => $data1,
            'periodo2' => $data2
        ]]);
    }

    // REF-014: Enviar comentario sobre reporte
    public function sendReportComment(): void {
        require_login();
        require_role(['ESTUDIANTE', 'ADMIN']);
        
        $comentario = $_POST['comentario'] ?? '';
        $report_id = isset($_POST['report_id']) ? (int)$_POST['report_id'] : null;
        $u = current_user();

        if (!$comentario) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Debe ingresar un comentario']);
            return;
        }

        // Enviar como mensaje al tutor/administrador
        $messageModel = new Message();
        $asunto = "Comentario sobre reporte" . ($report_id ? " #$report_id" : "");
        
        // Enviar a rol DOCENTE y ADMIN
        $sent = $messageModel->send($u['id'], null, 'ADMIN', $asunto, $comentario);
        
        if ($sent) {
            echo json_encode(['status' => 'success', 'message' => 'Comentario enviado exitosamente']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Error de conexión, inténtelo más tarde']);
        }
    }

    // ============================================
    // MÉTODOS AUXILIARES
    // ============================================

    private function exportToCSV(array $report): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_' . $report['id'] . '.csv"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        fputcsv($output, ['Título', $report['titulo']]);
        fputcsv($output, ['Tipo', $report['tipo']]);
        fputcsv($output, ['Descripción', $report['descripcion']]);
        fputcsv($output, ['Periodo Inicio', $report['periodo_inicio']]);
        fputcsv($output, ['Periodo Fin', $report['periodo_fin']]);
        fputcsv($output, ['Creado por', $report['creator_name']]);
        fputcsv($output, ['Fecha', $report['created_at']]);
        
        fclose($output);
        exit;
    }

    private function exportArrayToCSV(array $data, string $filename): void {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        // Escribir datos de forma recursiva
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                fputcsv($output, [$key]);
                if (isset($value[0]) && is_array($value[0])) {
                    // Array de arrays
                    $headers = array_keys($value[0]);
                    fputcsv($output, $headers);
                    foreach ($value as $row) {
                        fputcsv($output, $row);
                    }
                } else {
                    foreach ($value as $k => $v) {
                        fputcsv($output, [$k, $v]);
                    }
                }
            } else {
                fputcsv($output, [$key, $value]);
            }
        }
        
        fclose($output);
        exit;
    }
}
