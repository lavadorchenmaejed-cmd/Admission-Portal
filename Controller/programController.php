<?php

class ProgramController {
    private $model;

    public function __construct(Program $model) {
        $this->model = $model;
    }

    public function getAll() {
        return $this->model->getAll();
    }

    public function getById($program_id) {
        return $this->model->getById($program_id);
    }

    public function create(string $program_name, float $tuition): bool {
        return $this->model->create(trim($program_name), $tuition);
    }

    public function update(int $id, string $program_name, float $tuition): bool {
        return $this->model->update($id, trim($program_name), $tuition);
    }

    public function delete(int $id): bool {
        return $this->model->delete($id);
    }

    /**
     * Handle AJAX JSON requests sent to this page.
     * Call this BEFORE any HTML output.
     */
    public function handleAjax(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $raw    = file_get_contents('php://input');
        $data   = json_decode($raw, true);
        $action = $data['action'] ?? ($_POST['action'] ?? '');

        if (!$action) return; // not an AJAX call

        header('Content-Type: application/json');

        try {
            switch ($action) {

                case 'create':
                    $name    = trim($data['program_name'] ?? '');
                    $tuition = (float)($data['tuition'] ?? 0);
                    if ($name === '') { echo json_encode(['success' => false, 'message' => 'Program name is required.']); exit; }
                    $ok = $this->create($name, $tuition);
                    echo json_encode(['success' => $ok, 'message' => $ok ? 'Program created.' : 'Failed to create program.']);
                    break;

                case 'update':
                    $id      = (int)($data['program_id'] ?? 0);
                    $name    = trim($data['program_name'] ?? '');
                    $tuition = (float)($data['tuition'] ?? 0);
                    if (!$id || $name === '') { echo json_encode(['success' => false, 'message' => 'Invalid data.']); exit; }
                    $ok = $this->update($id, $name, $tuition);
                    echo json_encode(['success' => $ok, 'message' => $ok ? 'Program updated.' : 'Failed to update program.']);
                    break;

                case 'delete':
                    $id = (int)($data['program_id'] ?? 0);
                    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID.']); exit; }
                    $ok = $this->delete($id);
                    echo json_encode(['success' => $ok, 'message' => $ok ? 'Program deleted.' : 'Failed to delete program.']);
                    break;

                case 'get':
                    $id = (int)($data['program_id'] ?? 0);
                    $row = $this->getById($id);
                    echo json_encode(['success' => (bool)$row, 'program' => $row]);
                    break;

                default:
                    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}