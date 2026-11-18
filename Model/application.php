<?php
class Application {
    private $database;
    private $table = "application";

    public function __construct(mysqli $database) {
        $this->database = $database;
    }

    public function create($application_id, $document, $filePath, $date) {
        $stmt = $this->database->prepare("INSERT INTO {$this->table} 
            (application_id, document_type, file_path, submission_date ) 
            VALUES (?, ?, ?, ?)");
    
        if ($stmt === false) {
            throw new Exception("Prepare failed: " . $this->database->error);
        }

        $stmt->bind_param("isss", $application_id, $document, $filePath, $date);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        return $stmt->insert_id; // return the new document ID
    }
}

?>
