<?php
require_once __DIR__ . '/../models/Report.php';
require_once __DIR__ . '/../helpers/auth.php';

class ReportsController
{
    private Report $model;

    public function __construct()
    {
        $this->model = new Report();
    }

    private function requireReportRole(): array
    {
        require_login();
        require_role(['ADMIN', 'DOCENTE']);
        return current_user();
    }

    private function normalizeTypeForUser(string $type, array $user): ?string
    {
        if (!in_array($type, Report::TYPES, true)) {
            return null;
        }
        if (($user['rol'] ?? '') === 'DOCENTE' && !in_array($type, ['ACADEMIC_NOTES', 'ACADEMIC_ATTENDANCE'], true)) {
            return null;
        }
        return $type;
    }

    private function extractFilters(string $type): array
    {
        return match ($type) {
            'ACADEMIC_NOTES' => [
                'year' => (int) ($_POST['year'] ?? $_GET['year'] ?? date('Y')),
                'section_id' => (int) ($_POST['section_id'] ?? $_GET['section_id'] ?? 0),
                'subject_name' => trim((string) ($_POST['subject_name'] ?? $_GET['subject_name'] ?? '')),
            ],
            'ACADEMIC_ATTENDANCE' => [
                'section_id' => (int) ($_POST['section_id'] ?? $_GET['section_id'] ?? 0),
                'date_from' => trim((string) ($_POST['date_from'] ?? $_GET['date_from'] ?? '')),
                'date_to' => trim((string) ($_POST['date_to'] ?? $_GET['date_to'] ?? '')),
            ],
            'ADMIN_PAYMENTS' => [
                'year' => (int) ($_POST['year'] ?? $_GET['year'] ?? date('Y')),
                'month_key' => trim((string) ($_POST['month_key'] ?? $_GET['month_key'] ?? '')),
                'is_paid' => (string) ($_POST['is_paid'] ?? $_GET['is_paid'] ?? ''),
            ],
            'ADMIN_ENROLLMENTS_LEVEL', 'ADMIN_ENROLLMENTS_SECTION' => [
                'year' => (int) ($_POST['year'] ?? $_GET['year'] ?? date('Y')),
            ],
            default => [],
        };
    }

    public function sections(): void
    {
        $user = $this->requireReportRole();
        echo json_encode(['status' => 'success', 'data' => $this->model->listSectionsForUser($user)]);
    }

    public function list(): void
    {
        $user = $this->requireReportRole();
        $rows = $this->model->listForUser($user);
        foreach ($rows as &$row) {
            $row['filters'] = json_decode((string) ($row['filters_json'] ?? '{}'), true) ?: [];
        }
        echo json_encode(['status' => 'success', 'data' => $rows]);
    }

    public function create(): void
    {
        $user = $this->requireReportRole();
        $title = trim((string) ($_POST['title'] ?? ''));
        $type = $this->normalizeTypeForUser(trim((string) ($_POST['report_type'] ?? '')), $user);
        if ($title === '' || !$type) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Título o tipo inválido']);
            return;
        }
        $filters = $this->extractFilters($type);
        $id = $this->model->create($title, $type, (string) ($user['rol'] ?? ''), $filters, (int) ($user['id'] ?? 0));
        if (!$id) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo crear el reporte']);
            return;
        }
        echo json_encode(['status' => 'success', 'id' => $id]);
    }

    public function update(): void
    {
        $user = $this->requireReportRole();
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($id <= 0 || $title === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
            return;
        }
        $current = $this->model->getById($id);
        if (!$current) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Reporte no encontrado']);
            return;
        }
        $type = $this->normalizeTypeForUser((string) ($current['report_type'] ?? ''), $user);
        if (!$type) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Sin permisos']);
            return;
        }
        $filters = $this->extractFilters($type);
        if (!$this->model->update($id, $title, $filters, (int) ($user['id'] ?? 0))) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar el reporte']);
            return;
        }
        echo json_encode(['status' => 'success']);
    }

    public function delete(): void
    {
        $user = $this->requireReportRole();
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Falta el id']);
            return;
        }
        $current = $this->model->getById($id);
        if (!$current) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Reporte no encontrado']);
            return;
        }
        if (($user['rol'] ?? '') === 'DOCENTE' && ($current['scope_role'] ?? '') !== 'DOCENTE') {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Sin permisos']);
            return;
        }
        if (!$this->model->delete($id)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
            return;
        }
        echo json_encode(['status' => 'success']);
    }

    public function preview(): void
    {
        $user = $this->requireReportRole();
        $type = $this->normalizeTypeForUser(trim((string) ($_GET['report_type'] ?? '')), $user);
        if (!$type) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tipo inválido']);
            return;
        }
        $data = $this->model->buildDataset($type, $this->extractFilters($type), $user);
        echo json_encode(['status' => 'success', 'data' => $data]);
    }

    public function generatePdf(): void
    {
        $user = $this->requireReportRole();
        $reportId = (int) ($_GET['id'] ?? 0);
        $title = 'Reporte';
        $type = trim((string) ($_GET['report_type'] ?? ''));
        $filters = [];

        if ($reportId > 0) {
            $report = $this->model->getById($reportId);
            if (!$report) {
                http_response_code(404);
                echo 'Reporte no encontrado';
                return;
            }
            $type = (string) ($report['report_type'] ?? '');
            if (!$this->normalizeTypeForUser($type, $user)) {
                http_response_code(403);
                echo 'Sin permisos';
                return;
            }
            $title = (string) ($report['title'] ?? 'Reporte');
            $filters = json_decode((string) ($report['filters_json'] ?? '{}'), true) ?: [];
        } else {
            $type = $this->normalizeTypeForUser($type, $user) ?? '';
            if ($type === '') {
                http_response_code(400);
                echo 'Tipo inválido';
                return;
            }
            $title = trim((string) ($_GET['title'] ?? 'Reporte generado'));
            $filters = $this->extractFilters($type);
        }

        $dataset = $this->model->buildDataset($type, $filters, $user);
        if (!empty($dataset['meta']['error'])) {
            http_response_code(403);
            echo $dataset['meta']['error'];
            return;
        }

        $pdf = $this->buildPdfDocument($title, $type, $dataset);
        header_remove('Content-Type');
        header('Content-Type: application/pdf');
        $filename = preg_replace('/[^A-Za-z0-9_-]+/', '_', $title) . '.pdf';
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $pdf;
    }

    private function buildPdfDocument(string $title, string $type, array $dataset): string
    {
        $lines = [];
        $lines[] = 'New Hope School';
        $lines[] = $title;
        $lines[] = 'Tipo: ' . $type;
        $lines[] = 'Fecha de generación: ' . date('Y-m-d H:i');
        if (!empty($dataset['meta'])) {
            foreach ($dataset['meta'] as $k => $v) {
                if ($v === '' || $v === null || $k === 'error') continue;
                $lines[] = strtoupper((string) $k) . ': ' . (is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
            }
        }
        $lines[] = str_repeat('-', 100);
        $headers = $dataset['headers'] ?? [];
        if ($headers) {
            $lines[] = $this->formatRow($headers);
            $lines[] = str_repeat('-', 100);
        }
        foreach (($dataset['rows'] ?? []) as $row) {
            $lines[] = $this->formatRow($row);
        }
        if (empty($dataset['rows'])) {
            $lines[] = 'Sin datos para los filtros seleccionados.';
        }
        return $this->simpleTextPdf($lines);
    }

    private function formatRow(array $row): string
    {
        $clean = array_map(static fn($v) => trim(preg_replace('/\s+/', ' ', (string) $v)), $row);
        return implode(' | ', $clean);
    }

    private function simpleTextPdf(array $lines): string
    {
        $pageWidth = 595; $pageHeight = 842; $marginLeft = 40; $marginTop = 50; $lineHeight = 14;
        $maxLinesPerPage = 50;
        $pages = array_chunk($this->wrapLines($lines, 105), $maxLinesPerPage);
        $objects = [];
        $pageIds = [];
        $fontObjId = 1;
        $objects[$fontObjId] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";
        $nextId = 2;
        foreach ($pages as $pageLines) {
            $content = "BT\n/F1 10 Tf\n";
            $y = $pageHeight - $marginTop;
            foreach ($pageLines as $line) {
                $content .= sprintf("1 0 0 1 %d %d Tm (%s) Tj\n", $marginLeft, $y, $this->pdfEscape($this->latinize($line)));
                $y -= $lineHeight;
            }
            $content .= "ET";
            $contentObjId = $nextId++;
            $objects[$contentObjId] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
            $pageObjId = $nextId++;
            $pageIds[] = $pageObjId;
            $objects[$pageObjId] = "<< /Type /Page /Parent PAGES_ID 0 R /MediaBox [0 0 $pageWidth $pageHeight] /Resources << /Font << /F1 $fontObjId 0 R >> >> /Contents $contentObjId 0 R >>";
        }
        $pagesObjId = $nextId++;
        $kids = implode(' ', array_map(fn($id) => "$id 0 R", $pageIds));
        $objects[$pagesObjId] = "<< /Type /Pages /Kids [ $kids ] /Count " . count($pageIds) . " >>";
        foreach ($pageIds as $pid) {
            $objects[$pid] = str_replace('PAGES_ID', (string) $pagesObjId, $objects[$pid]);
        }
        $catalogObjId = $nextId++;
        $objects[$catalogObjId] = "<< /Type /Catalog /Pages $pagesObjId 0 R >>";

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . ($catalogObjId + 1) . "\n";
        $pdf .= sprintf("%010d %05d f \n", 0, 65535);
        for ($i = 1; $i <= $catalogObjId; $i++) {
            $pdf .= sprintf("%010d %05d n \n", $offsets[$i] ?? 0, 0);
        }
        $pdf .= "trailer\n<< /Size " . ($catalogObjId + 1) . " /Root $catalogObjId 0 R >>\nstartxref\n$xrefPos\n%%EOF";
        return $pdf;
    }

    private function wrapLines(array $lines, int $width): array
    {
        $wrapped = [];
        foreach ($lines as $line) {
            $line = $this->latinize((string) $line);
            if (strlen($line) <= $width) {
                $wrapped[] = $line;
                continue;
            }
            $parts = wordwrap($line, $width, "\n", true);
            foreach (explode("\n", $parts) as $piece) {
                $wrapped[] = $piece;
            }
        }
        return $wrapped;
    }

    private function pdfEscape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function latinize(string $text): string
    {
        $map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','ñ'=>'n','Ñ'=>'N','ü'=>'u','Ü'=>'U','–'=>'-','—'=>'-'];
        $text = strtr($text, $map);
        return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
    }
}
