<?php

class Program {
    private $database;
    private $table = "programs";

    public function __construct(mysqli $database) {
        $this->database = $database;
    }

    // Get all programs
    public function getAll() {
        $stmt = $this->database->prepare("SELECT program_id, program_name, tuition FROM {$this->table} ORDER BY program_id ASC");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    // Get single program
    public function getById($id) {
        $stmt = $this->database->prepare("SELECT * FROM {$this->table} WHERE program_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Create a new program
    public function create(string $program_name, float $tuition): bool {
        $stmt = $this->database->prepare("INSERT INTO {$this->table} (program_name, tuition) VALUES (?, ?)");
        $stmt->bind_param("sd", $program_name, $tuition);
        return $stmt->execute();
    }

    // Update an existing program
    public function update(int $id, string $program_name, float $tuition): bool {
        $stmt = $this->database->prepare("UPDATE {$this->table} SET program_name = ?, tuition = ? WHERE program_id = ?");
        $stmt->bind_param("sdi", $program_name, $tuition, $id);
        return $stmt->execute();
    }

    // Delete a program
    public function delete(int $id): bool {
        $stmt = $this->database->prepare("DELETE FROM {$this->table} WHERE program_id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    public function getApplicantWithProgram(mysqli $conn, int $user_Id): ?array {
        $sql = "
            SELECT a.*, p.program_name
            FROM applicants a
            JOIN programs p ON a.program_id = p.program_id
            WHERE a.user_Id = ?
            LIMIT 1
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_Id);
        $stmt->execute();
        $result = $stmt->get_result();
        $application = $result->fetch_assoc();
        return $application ?: null;
    }
}