<?php

class ReportController {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    /**
     * Get applicants with optional filters
     */
    public function getApplicantsReport($statusFilter = null, $dateFrom = null, $dateTo = null, $programFilter = null) {
        try {
            // Special handling for Approved status - show applicants who have at least one approved application
            if ($statusFilter === 'Approved') {
                $query = "
                    SELECT
                        a.applicant_id,
                        u.username,
                        a.first_name,
                        a.last_name,
                        a.email,
                        a.contact_number,
                        a.address,
                        p.program_name,
                        p.program_id,
                        'Approved' as status,
                        MAX(app.submission_date) as submission_date,
                        a.created_at
                    FROM applicants a
                    INNER JOIN application app ON a.applicant_id = app.applicant_id AND app.status = 'Approved'
                    LEFT JOIN users u ON a.user_id = u.user_id
                    LEFT JOIN programs p ON a.program_id = p.program_id
                    WHERE 1=1
                ";

                $params = [];
                $types = '';

                // Add program filter
                if (!empty($programFilter)) {
                    $query .= " AND a.program_id = ?";
                    $params[] = $programFilter;
                    $types .= 'i';
                }

                // Add date range filters
                if (!empty($dateFrom)) {
                    $query .= " AND DATE(a.created_at) >= ?";
                    $params[] = $dateFrom;
                    $types .= 's';
                }

                if (!empty($dateTo)) {
                    $query .= " AND DATE(a.created_at) <= ?";
                    $params[] = $dateTo;
                    $types .= 's';
                }

                $query .= " GROUP BY a.applicant_id, u.username, a.first_name, a.last_name, a.email, a.contact_number, a.address, p.program_name, p.program_id, a.created_at";

                $query .= " ORDER BY a.created_at DESC";
            } else {
                // Original query for other statuses
                $query = "
                    SELECT DISTINCT
                        a.applicant_id,
                        u.username,
                        a.first_name,
                        a.last_name,
                        a.email,
                        a.contact_number,
                        a.address,
                        p.program_name,
                        p.program_id,
                        COALESCE(latest_app.status, 'Pending') as status,
                        latest_app.submission_date,
                        a.created_at
                    FROM applicants a
                    LEFT JOIN users u ON a.user_id = u.user_id
                    LEFT JOIN programs p ON a.program_id = p.program_id
                    LEFT JOIN (
                        SELECT app1.applicant_id, app1.status, app1.submission_date
                        FROM application app1
                        WHERE app1.application_id = (
                            SELECT MAX(app2.application_id)
                            FROM application app2
                            WHERE app2.applicant_id = app1.applicant_id
                        )
                    ) latest_app ON a.applicant_id = latest_app.applicant_id
                    WHERE 1=1
                ";

                $params = [];
                $types = '';

                // Add status filter
                if (!empty($statusFilter)) {
                    if ($statusFilter === 'Pending') {
                        $query .= " AND (latest_app.status IS NULL OR latest_app.status IN ('Pending', 'Under Review'))";
                    } else {
                        $query .= " AND latest_app.status = ?";
                        $params[] = $statusFilter;
                        $types .= 's';
                    }
                }

                // Add program filter
                if (!empty($programFilter)) {
                    $query .= " AND a.program_id = ?";
                    $params[] = $programFilter;
                    $types .= 'i';
                }

                // Add date range filters
                if (!empty($dateFrom)) {
                    $query .= " AND DATE(a.created_at) >= ?";
                    $params[] = $dateFrom;
                    $types .= 's';
                }

                if (!empty($dateTo)) {
                    $query .= " AND DATE(a.created_at) <= ?";
                    $params[] = $dateTo;
                    $types .= 's';
                }

                $query .= " ORDER BY a.created_at DESC";
            }

            $stmt = $this->conn->prepare($query);

            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            if (!$stmt->execute()) {
                throw new Exception("Query execution failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $applicants = [];

            while ($row = $result->fetch_assoc()) {
                $applicants[] = $row;
            }

            $stmt->close();
            return $applicants;
        } catch (Exception $e) {
            error_log("ReportController Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get report summary statistics
     */
    public function getReportSummary($statusFilter = null, $dateFrom = null, $dateTo = null, $programFilter = null) {
        try {
            $query = "
                SELECT 
                    COUNT(DISTINCT a.applicant_id) as total_applicants,
                    COUNT(DISTINCT CASE WHEN app.status = 'Approved' THEN a.applicant_id END) as approved,
                    COUNT(DISTINCT CASE WHEN app.status = 'Rejected' THEN a.applicant_id END) as rejected,
                    COUNT(DISTINCT CASE WHEN app.status IS NULL OR app.status IN ('Pending', 'Under Review') THEN a.applicant_id END) as pending
                FROM applicants a
                LEFT JOIN application app ON a.applicant_id = app.applicant_id
                WHERE 1=1
            ";

            $params = [];
            $types = '';

            if (!empty($statusFilter)) {
                if ($statusFilter === 'Pending') {
                    $query .= " AND (app.status IS NULL OR app.status = 'Pending')";
                } else {
                    $query .= " AND app.status = ?";
                    $params[] = $statusFilter;
                    $types .= 's';
                }
            }

            if (!empty($programFilter)) {
                $query .= " AND a.program_id = ?";
                $params[] = $programFilter;
                $types .= 'i';
            }

            if (!empty($dateFrom)) {
                $query .= " AND DATE(a.created_at) >= ?";
                $params[] = $dateFrom;
                $types .= 's';
            }

            if (!empty($dateTo)) {
                $query .= " AND DATE(a.created_at) <= ?";
                $params[] = $dateTo;
                $types .= 's';
            }

            $stmt = $this->conn->prepare($query);
            
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            if (!$stmt->execute()) {
                throw new Exception("Query execution failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            $summary = $result->fetch_assoc();
            $stmt->close();

            return $summary ? $summary : [
                'total_applicants' => 0,
                'approved' => 0,
                'rejected' => 0,
                'pending' => 0
            ];
        } catch (Exception $e) {
            error_log("ReportController Error: " . $e->getMessage());
            return [
                'total_applicants' => 0,
                'approved' => 0,
                'rejected' => 0,
                'pending' => 0
            ];
        }
    }

    /**
     * Get all programs
     */
    public function getPrograms() {
        try {
            $query = "
                SELECT program_id, program_name 
                FROM programs 
                ORDER BY program_name ASC
            ";
            $result = $this->conn->query($query);
            
            if (!$result) {
                throw new Exception("Query failed: " . $this->conn->error);
            }

            $programs = [];
            while ($row = $result->fetch_assoc()) {
                $programs[] = $row;
            }

            return $programs;
        } catch (Exception $e) {
            error_log("ReportController Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all unique statuses
     */
    public function getStatuses() {
        try {
            // Get database statuses
            $query = "
                SELECT DISTINCT status 
                FROM application 
                WHERE status IS NOT NULL 
                ORDER BY status ASC
            ";
            $result = $this->conn->query($query);
            
            if (!$result) {
                throw new Exception("Query failed: " . $this->conn->error);
            }

            $dbStatuses = [];
            while ($row = $result->fetch_assoc()) {
                if (!empty($row['status'])) {
                    $dbStatuses[] = $row['status'];
                }
            }

            // Define all possible statuses
            $allStatuses = ['Pending', 'Under Review', 'Approved', 'Rejected'];
            
            // Combine and return unique values
            $statuses = array_unique(array_merge($dbStatuses, $allStatuses));
            sort($statuses);
            
            return array_values($statuses);
        } catch (Exception $e) {
            error_log("ReportController Error: " . $e->getMessage());
            // Return default statuses if query fails
            return ['Pending', 'Under Review', 'Approved', 'Rejected'];
        }
    }

    /**
     * Export data to CSV
     */
    public function exportToCSV($applicants) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="applicants_report_' . date('Y-m-d_H-i-s') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // School header
        fputcsv($output, ['Northeastern Cebu Colleges, Inc.']);
        fputcsv($output, ['Applicant Report']);
        fputcsv($output, ['Generated on', date('F d, Y g:i A')]);
        fputcsv($output, []);

        // CSV headers
        fputcsv($output, [
            'Program',
            'Applicant ID',
            'Username',
            'Full Name',
            'Email',
            'Status'
        ]);

        // Data grouped by program
        $grouped = [];
        foreach ($applicants as $app) {
            $program = $app['program_name'] ?? 'Unassigned';
            $grouped[$program][] = $app;
        }

        foreach ($grouped as $program => $appsInProgram) {
            fputcsv($output, []);
            fputcsv($output, ["Program: $program"]);
            foreach ($appsInProgram as $app) {
                fputcsv($output, [
                    $program,
                    $app['applicant_id'],
                    $app['username'],
                    trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')),
                    $app['email'],
                    $app['status'] ?? 'Pending'
                ]);
            }
        }

        fclose($output);
        exit();
    }

    /**
     * Export data to Excel (HTML table that Excel can open)
     */
    public function exportToExcel($applicants, $summary, $filters) {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="applicants_report_' . date('Y-m-d_H-i-s') . '.xls"');
        header('Cache-Control: max-age=0');

        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Applicants Report</title>';
        echo '<style>';
        echo 'body { font-family: Arial, sans-serif; }';
        echo 'table { border-collapse: collapse; width: 100%; }';
        echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
        echo 'th { background-color: #f2f2f2; font-weight: bold; }';
        echo '.summary { margin-bottom: 20px; }';
        echo '.summary h3 { color: #333; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';

        // Report Header
        echo '<div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;">';
        echo '<img src="../img/logo.png" alt="NCC Logo" style="height:60px;width:auto;">';
        echo '<div>'; 
        echo '<h2 style="margin:0;">Northeastern Cebu Colleges, Inc.</h2>';
        echo '<h3 style="margin:0;">NCC Admission Portal - Applicants Report</h3>';
        echo '<p style="margin:0;font-size:12px;">Generated on: ' . date('F d, Y \a\t g:i A') . '</p>';
        echo '</div>';
        echo '</div>';

        // Data grouped by program list
        $grouped = [];
        foreach ($applicants as $app) {
            $program = $app['program_name'] ?? 'Unassigned';
            $grouped[$program][] = $app;
        }

        echo '<div style="margin-top:20px;">';
        echo '<h3>Applicants by Program</h3>';
        foreach ($grouped as $program => $appsInProgram) {
            echo '<strong>' . htmlspecialchars($program) . ' (' . count($appsInProgram) . ')</strong><br>';
            echo '<ul style="margin-top:0;margin-bottom:12px;">';
            foreach ($appsInProgram as $app) {
                echo '<li>' . htmlspecialchars(trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? ''))) . ' (ID: ' . htmlspecialchars($app['applicant_id']) . ')</li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        // Filters Applied
        if (!empty($filters)) {
            echo '<h3>Applied Filters:</h3>';
            echo '<ul>';
            foreach ($filters as $key => $value) {
                if (!empty($value)) {
                    echo '<li>' . ucfirst(str_replace('_', ' ', $key)) . ': ' . htmlspecialchars($value) . '</li>';
                }
            }
            echo '</ul>';
        }

        // Summary
        echo '<div class="summary">';
        echo '<h3>Summary Statistics</h3>';
        echo '<p>Total Applicants: ' . ($summary['total_applicants'] ?? 0) . '</p>';
        echo '<p>Approved: ' . ($summary['approved'] ?? 0) . '</p>';
        echo '<p>Rejected: ' . ($summary['rejected'] ?? 0) . '</p>';
        echo '<p>Pending: ' . ($summary['pending'] ?? 0) . '</p>';
        echo '</div>';

        // Data Table
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Applicant ID</th>';
        echo '<th>Username</th>';
        echo '<th>First Name</th>';
        echo '<th>Last Name</th>';
        echo '<th>Email</th>';
        echo '<th>Contact Number</th>';
        echo '<th>Address</th>';
        echo '<th>Program</th>';
        echo '<th>Status</th>';
        echo '<th>Submission Date</th>';
        echo '<th>Created Date</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($applicants as $app) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($app['applicant_id']) . '</td>';
            echo '<td>' . htmlspecialchars($app['username']) . '</td>';
            echo '<td>' . htmlspecialchars($app['first_name']) . '</td>';
            echo '<td>' . htmlspecialchars($app['last_name']) . '</td>';
            echo '<td>' . htmlspecialchars($app['email']) . '</td>';
            echo '<td>' . htmlspecialchars($app['contact_number'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($app['address'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($app['program_name'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($app['status'] ?? 'Pending') . '</td>';
            echo '<td>' . ($app['submission_date'] ? date('M d, Y', strtotime($app['submission_date'])) : 'N/A') . '</td>';
            echo '<td>' . date('M d, Y', strtotime($app['created_at'])) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</body>';
        echo '</html>';
        exit();
    }

    /**
     * Export data to PDF (HTML that can be printed to PDF)
     */
    public function exportToPDF($applicants, $summary, $filters) {
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="applicants_report_' . date('Y-m-d_H-i-s') . '.html"');

        echo '<!DOCTYPE html>';
        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Applicants Report - PDF Export</title>';
        echo '<style>';
        echo '@page { margin: 1in; }';
        echo 'body { font-family: "Times New Roman", serif; font-size: 12px; line-height: 1.4; }';
        echo 'h1, h2, h3 { color: #333; margin-bottom: 10px; }';
        echo 'table { border-collapse: collapse; width: 100%; margin-bottom: 20px; font-size: 10px; }';
        echo 'th, td { border: 1px solid #000; padding: 5px; text-align: left; }';
        echo 'th { background-color: #f0f0f0; font-weight: bold; }';
        echo '.header { margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }';
        echo '.summary { margin-bottom: 20px; }';
        echo '.summary-grid { display: table; width: 100%; }';
        echo '.summary-item { display: table-cell; width: 25%; text-align: center; padding: 10px; background: #f9f9f9; margin: 5px; }';
        echo '.filters { margin-bottom: 20px; }';
        echo '.program-group { margin-bottom: 12px; }';
        echo '.program-group ul { margin: 4px 0 10px 18px; }';
        echo '.page-break { page-break-before: always; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';

        // Header
        echo '<div class="header" style="display:flex;align-items:center;gap:12px;">';
        echo '<img src="../img/logo.png" alt="NCC Logo" style="height:70px;width:auto;" />';
        echo '<div>';
        echo '<h1 style="margin:0;">Northeastern Cebu Colleges, Inc.</h1>';
        echo '<h2 style="margin:0;">Admission Portal - Applicants Report</h2>';
        echo '<p style="margin:0;">Generated on: ' . date('F d, Y \a\t g:i A') . '</p>';
        echo '</div>';
        echo '</div>';

        // Data grouped by program list
        $grouped = [];
        foreach ($applicants as $app) {
            $program = $app['program_name'] ?? 'Unassigned';
            $grouped[$program][] = $app;
        }

        echo '<div class="program-list" style="margin-top:20px;">';
        echo '<h3>Applicants by Program</h3>';
        foreach ($grouped as $program => $appsInProgram) {
            echo '<div class="program-group">';
            echo '<strong>' . htmlspecialchars($program) . ' (' . count($appsInProgram) . ')</strong>';
            echo '<ul>';
            foreach ($appsInProgram as $app) {
                echo '<li>' . htmlspecialchars(trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? ''))) . ' (ID: ' . htmlspecialchars($app['applicant_id']) . ')</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        echo '</div>';

        // Filters Applied
        if (!empty($filters)) {
            echo '<div class="filters">';
            echo '<h3>Applied Filters:</h3>';
            echo '<ul>';
            foreach ($filters as $key => $value) {
                if (!empty($value)) {
                    echo '<li>' . ucfirst(str_replace('_', ' ', $key)) . ': ' . htmlspecialchars($value) . '</li>';
                }
            }
            echo '</ul>';
            echo '</div>';
        }

        // Summary
        echo '<div class="summary">';
        echo '<h3>Summary Statistics</h3>';
        echo '<div class="summary-grid">';
        echo '<div class="summary-item"><strong>Total Applicants</strong><br>' . ($summary['total_applicants'] ?? 0) . '</div>';
        echo '<div class="summary-item"><strong>Approved</strong><br>' . ($summary['approved'] ?? 0) . '</div>';
        echo '<div class="summary-item"><strong>Rejected</strong><br>' . ($summary['rejected'] ?? 0) . '</div>';
        echo '<div class="summary-item"><strong>Pending</strong><br>' . ($summary['pending'] ?? 0) . '</div>';
        echo '</div>';
        echo '</div>';

        // Data Table
        echo '<h3>Applicant Details</h3>';
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Username</th>';
        echo '<th>Name</th>';
        echo '<th>Email</th>';
        echo '<th>Contact</th>';
        echo '<th>Address</th>';
        echo '<th>Program</th>';
        echo '<th>Status</th>';
        echo '<th>Submitted</th>';
        echo '<th>Applied</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $count = 0;
        foreach ($applicants as $app) {
            if ($count > 0 && $count % 25 == 0) {
                echo '</tbody></table>';
                echo '<div class="page-break"></div>';
                echo '<table><thead><tr>';
                echo '<th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Contact</th><th>Address</th><th>Program</th><th>Status</th><th>Submitted</th><th>Applied</th>';
                echo '</tr></thead><tbody>';
            }

            echo '<tr>';
            echo '<td>' . htmlspecialchars($app['applicant_id']) . '</td>';
            echo '<td>' . htmlspecialchars($app['username']) . '</td>';
            echo '<td>' . htmlspecialchars(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? '')) . '</td>';
            echo '<td>' . htmlspecialchars($app['email']) . '</td>';
            echo '<td>' . htmlspecialchars($app['contact_number'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($app['address'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($app['program_name'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($app['status'] ?? 'Pending') . '</td>';
            echo '<td>' . ($app['submission_date'] ? date('M d, Y', strtotime($app['submission_date'])) : 'N/A') . '</td>';
            echo '<td>' . date('M d, Y', strtotime($app['created_at'])) . '</td>';
            echo '</tr>';
            $count++;
        }

        echo '</tbody>';
        echo '</table>';

        echo '<div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">';
        echo '<p>This report was generated from the NCC Admission Portal system.</p>';
        echo '</div>';

        echo '</body>';
        echo '</html>';
        exit();
    }

    /**
     * Export data to DOC (Word document)
     */
    public function exportToDOC($applicants, $summary, $filters) {
        header('Content-Type: application/msword');
        header('Content-Disposition: attachment; filename="applicants_report_' . date('Y-m-d_H-i-s') . '.doc"');

        echo '<html>';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<title>Applicants Report</title>';
        echo '<style>';
        echo 'body { font-family: "Times New Roman", serif; font-size: 12px; line-height: 1.4; }';
        echo 'h1, h2, h3 { color: #000; margin-bottom: 10px; }';
        echo 'table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }';
        echo 'th, td { border: 1px solid #000; padding: 5px; text-align: left; }';
        echo 'th { background-color: #f0f0f0; font-weight: bold; }';
        echo '.header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 20px; }';
        echo '.summary { margin-bottom: 20px; }';
        echo '.summary-grid { display: table; width: 100%; }';
        echo '.summary-item { display: table-cell; width: 25%; text-align: center; padding: 10px; background: #f9f9f9; margin: 5px; }';
        echo '.filters { margin-bottom: 20px; }';
        echo '</style>';
        echo '</head>';
        echo '<body>';

        // Header
        echo '<div class="header" style="display:flex;align-items:center;gap:12px;">';
        echo '<img src="../img/logo.png" alt="NCC Logo" style="height:70px;width:auto;" />';
        echo '<div>';
        echo '<h1 style="margin:0;">Northeastern Cebu Colleges, Inc.</h1>';
        echo '<h2 style="margin:0;">Admission Portal - Applicants Report</h2>';
        echo '<p style="margin:0;">Generated on: ' . date('F d, Y \a\t g:i A') . '</p>';
        echo '</div>';
        echo '</div>';

        // Data grouped by program list
        $grouped = [];
        foreach ($applicants as $app) {
            $program = $app['program_name'] ?? 'Unassigned';
            $grouped[$program][] = $app;
        }

        echo '<div style="margin-top:20px;">';
        echo '<h3>Applicants by Program</h3>';
        foreach ($grouped as $program => $appsInProgram) {
            echo '<div style="margin-bottom:10px;">';
            echo '<strong>' . htmlspecialchars($program) . ' (' . count($appsInProgram) . ')</strong>';
            echo '<ul style="margin:4px 0 10px 20px;">';
            foreach ($appsInProgram as $app) {
                echo '<li>' . htmlspecialchars(trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? ''))) . ' (ID: ' . htmlspecialchars($app['applicant_id']) . ')</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        echo '</div>';

        // Filters Applied
        if (!empty($filters)) {
            echo '<div class="filters">';
            echo '<h3>Applied Filters:</h3>';
            echo '<ul>';
            foreach ($filters as $key => $value) {
                if (!empty($value)) {
                    echo '<li>' . ucfirst(str_replace('_', ' ', $key)) . ': ' . htmlspecialchars($value) . '</li>';
                }
            }
            echo '</ul>';
            echo '</div>';
        }

        // Summary
        echo '<div class="summary">';
        echo '<h3>Summary Statistics</h3>';
        echo '<div class="summary-grid">';
        echo '<div class="summary-item"><strong>Total Applicants</strong><br>' . ($summary['total_applicants'] ?? 0) . '</div>';
        echo '<div class="summary-item"><strong>Approved</strong><br>' . ($summary['approved'] ?? 0) . '</div>';
        echo '<div class="summary-item"><strong>Rejected</strong><br>' . ($summary['rejected'] ?? 0) . '</div>';
        echo '<div class="summary-item"><strong>Pending</strong><br>' . ($summary['pending'] ?? 0) . '</div>';
        echo '</div>';
        echo '</div>';

        // Data Table
        echo '<h3>Applicant Details</h3>';
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Applicant ID</th>';
        echo '<th>Username</th>';
        echo '<th>First Name</th>';
        echo '<th>Last Name</th>';
        echo '<th>Email</th>';
        echo '<th>Contact Number</th>';
        echo '<th>Address</th>';
        echo '<th>Program</th>';
        echo '<th>Status</th>';
        echo '<th>Submission Date</th>';
        echo '<th>Created Date</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($applicants as $app) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($app['applicant_id']) . '</td>';
            echo '<td>' . htmlspecialchars($app['username']) . '</td>';
            echo '<td>' . htmlspecialchars($app['first_name']) . '</td>';
            echo '<td>' . htmlspecialchars($app['last_name']) . '</td>';
            echo '<td>' . htmlspecialchars($app['email']) . '</td>';
            echo '<td>' . htmlspecialchars($app['contact_number'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($app['address'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($app['program_name'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($app['status'] ?? 'Pending') . '</td>';
            echo '<td>' . ($app['submission_date'] ? date('M d, Y', strtotime($app['submission_date'])) : 'N/A') . '</td>';
            echo '<td>' . date('M d, Y', strtotime($app['created_at'])) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        echo '<div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">';
        echo '<p>This report was generated from the NCC Admission Portal system.</p>';
        echo '</div>';

        echo '</body>';
        echo '</html>';
        exit();
    }
}

?>
