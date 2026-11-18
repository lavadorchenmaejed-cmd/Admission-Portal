<?php
session_start();
require_once __DIR__ . '/../database.php';
$config = require __DIR__ . '/../config.php';
$db = new Database($config);
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicant_id = intval($_POST['applicant_id'] ?? 0);
    $document     = $_POST['document'] ?? '';

    if ($applicant_id && $document) {
        // Fetch the file path from DB
        $stmt = $conn->prepare("SELECT file_path FROM application WHERE applicant_id = ? AND document = ? LIMIT 1");
        $stmt->bind_param("is", $applicant_id, $document);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $file_path = __DIR__ . '/../uploads/photos/' . basename($row['file_path']);
            if (file_exists($file_path)) {
                unlink($file_path); // Delete the file
            }
        }
        $stmt->close();

        // Delete record from database
        $stmt2 = $conn->prepare("DELETE FROM application WHERE applicant_id = ? AND document = ?");
        $stmt2->bind_param("is", $applicant_id, $document);
        $stmt2->execute();
        $stmt2->close();

        $_SESSION['success'] = "Document deleted successfully!";
    }
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit();