<?php
class User {
    private $database;
    private $table = "user";

    public function __construct(mysqli $database) {
        $this->database = $database;
    }

    // Create user
public function create($username, $password, $email, $role = 'applicant') {
    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $this->database->prepare(
        "INSERT INTO {$this->table} (username, password, email, role) VALUES (?, ?, ?, ?)"
    );

    $stmt->bind_param("ssss", $username, $hashed, $email, $role);

    if (!$stmt->execute()) return false;

    return $this->database->insert_id;
}

    // ✅ ADDED: Find user by ID (needed by UserController::getByUserId)
    public function findById($user_Id) {
        $stmt = $this->database->prepare("SELECT * FROM {$this->table} WHERE user_Id = ? LIMIT 1");
        $stmt->bind_param("i", $user_Id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Find user by username
    public function findByUsername($username) {
        $stmt = $this->database->prepare("SELECT * FROM {$this->table} WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    // Verify credentials and return user with role
    public function verifyCredentials($username, $password) {
        $stmt = $this->database->prepare("SELECT user_Id, username, password, role FROM {$this->table} WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
           
            return $user;
        }
        return false;
    }

    // Get applicant record linked to a user
    public function getApplicantByUserId($userId) {
        $stmt = $this->database->prepare("SELECT * FROM applicants WHERE user_Id = ? LIMIT 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function updateEmail($user_Id, $email) {
        $stmt = $this->database->prepare("UPDATE {$this->table} SET email = ? WHERE user_Id = ?");
        $stmt->bind_param("si", $email, $user_Id);
        return $stmt->execute();
    }
}