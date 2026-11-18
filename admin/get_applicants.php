<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_Id'])) {
    echo json_encode([]);
    exit();
}

require_once __DIR__ . '/../database.php';
$config = require __DIR__ . '/../config.php';

$db   = new Database($config);
$conn = $db->getConnection();

$applicantId = isset($_GET['applicant_id']) ? (int)$_GET['applicant_id'] : 0;
if (!$applicantId) { echo json_encode([]); exit(); }

$docs = [];

$stmt = $conn->prepare("
    SELECT
        d.document_id,
        d.document_name,
        d.file_path
    FROM application a
    INNER JOIN documents d ON d.document_id = a.document_id
    WHERE a.applicant_id = ?
    ORDER BY d.document_id ASC
");

if ($stmt) {
    $stmt->bind_param("i", $applicantId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) $docs[] = $row;
    }
    $stmt->close();
}

// __DIR__ = htdocs/admin/
// root    = htdocs/
$root    = realpath(__DIR__ . '/../') . '/';
$webBase = '../'; // from admin/ one level up to htdocs/

$output = [];
foreach ($docs as $doc) {
    $raw = trim($doc['file_path'] ?? '');
    if (!$raw) continue;

    $basename = basename(str_replace('\\', '/', $raw));
    $webPath  = null;

    $candidates = [
        'uploads/photos/',
        'uploads/documents/',
        'uploads/',
    ];

    foreach ($candidates as $dir) {
        if (file_exists($root . $dir . $basename)) {
            $webPath = $webBase . $dir . $basename;
            break;
        }
    }

    // Recursive search inside uploads/ as safety net
    if (!$webPath && is_dir($root . 'uploads/')) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root . 'uploads/', FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iter as $file) {
            if ($file->isFile() && $file->getFilename() === $basename) {
                $relative = str_replace('\\', '/', str_replace($root, '', $file->getRealPath()));
                $webPath  = $webBase . $relative;
                break;
            }
        }
    }

    // Last resort — build the full public URL directly
    if (!$webPath) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host     = $_SERVER['HTTP_HOST'];
        $webPath  = $protocol . '://' . $host . '/uploads/photos/' . $basename;
    }

    $output[] = [
        'document_name' => $doc['document_name'],
        'file_path'     => $raw,
        'web_path'      => $webPath,
        'uploaded_at'   => null,
    ];
}

echo json_encode(array_values($output));