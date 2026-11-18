<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_Id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../pages/log_in.php");
    exit();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) && !isset($_SESSION['user_Id'])) {
    header("Location: ../pages/log_in.php");
    exit();
}

require_once __DIR__ . '/../database.php';
$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/../Model/profile.php';
require_once __DIR__ . '/../Controller/userController.php';

$db   = new Database($config);
$conn = $db->getConnection();

include __DIR__ . '/../layout/admintop.php';

$profileController = new ProfileController(new Profile($conn));
$profile = $profileController->getAll();

$programs = [];
$progResult = $conn->query("SELECT program_id, program_name FROM programs");
if ($progResult) while ($row = $progResult->fetch_assoc())
    $programs[$row['program_id']] = $row['program_name'];

$schoolYears = [];
$syResult = $conn->query("SELECT * FROM school_year ORDER BY year_id DESC");
while ($row = $syResult->fetch_assoc()) $schoolYears[] = $row;
$currentSY = !empty($schoolYears) ? $schoolYears[0] : null;

// Stats — fetch real counts from the latest application per applicant
$r = mysqli_query($conn, "SELECT COUNT(*) as total FROM applicants");
$totalApplicants = $r ? mysqli_fetch_assoc($r)['total'] : 0;

// Pending: latest application status is NULL or 'Pending'
$r = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM (
        SELECT a.applicant_id,
               (SELECT app2.status FROM application app2
                WHERE app2.applicant_id = a.applicant_id
                ORDER BY app2.application_id DESC LIMIT 1) as latest_status
        FROM applicants a
    ) sub
    WHERE sub.latest_status IS NULL OR sub.latest_status = 'Pending'
");
$totalPending = $r ? mysqli_fetch_assoc($r)['total'] : 0;

// Approved
$r = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM (
        SELECT a.applicant_id,
               (SELECT app2.status FROM application app2
                WHERE app2.applicant_id = a.applicant_id
                ORDER BY app2.application_id DESC LIMIT 1) as latest_status
        FROM applicants a
    ) sub
    WHERE sub.latest_status = 'Approved'
");
$totalAccepted = $r ? mysqli_fetch_assoc($r)['total'] : 0;

// Rejected
$r = mysqli_query($conn, "
    SELECT COUNT(*) as total FROM (
        SELECT a.applicant_id,
               (SELECT app2.status FROM application app2
                WHERE app2.applicant_id = a.applicant_id
                ORDER BY app2.application_id DESC LIMIT 1) as latest_status
        FROM applicants a
    ) sub
    WHERE sub.latest_status = 'Rejected'
");
$totalDeclined = $r ? mysqli_fetch_assoc($r)['total'] : 0;

// Per-program counts
$programStats = [];
foreach ($programs as $pid => $pname) {
    $pid_int = intval($pid);
    $r = mysqli_query($conn, "SELECT COUNT(*) as total FROM applicants WHERE program_id = $pid_int");
    $programStats[] = [
        'name'  => $pname,
        'total' => $r ? mysqli_fetch_assoc($r)['total'] : 0,
    ];
}

// Recent 5 applicants with real latest status
$recentApplicants = [];
$rr = $conn->query("
    SELECT a.applicant_id, a.Applicant_name, a.program_id, a.contact_number,
           COALESCE(
               (SELECT app2.status FROM application app2
                WHERE app2.applicant_id = a.applicant_id
                ORDER BY app2.application_id DESC LIMIT 1),
               'Pending'
           ) as status
    FROM applicants a
    ORDER BY a.applicant_id DESC LIMIT 5
");
if ($rr) while ($row = $rr->fetch_assoc()) $recentApplicants[] = $row;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — NCC Admission Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
  :root {
    --primary: #2563EB;
    --primary-light: #3B82F6;
    --primary-pale: rgba(37,99,235,0.08);
    --primary-border: rgba(37,99,235,0.18);
    --surface: #F1F5F9;
    --surface-card: #FFFFFF;
    --border: #E2E8F0;
    --border-strong: #CBD5E1;
    --text-primary: #0F172A;
    --text-secondary: #475569;
    --text-muted: #94A3B8;
    --gold: #D97706;
    --gold-light: #F59E0B;
    --gold-pale: rgba(217,119,6,0.08);
    --green: #059669;
    --green-pale: rgba(5,150,105,0.08);
    --red: #DC2626;
    --red-pale: rgba(220,38,38,0.08);
    --purple: #7C3AED;
    --purple-pale: rgba(124,58,237,0.08);
    --navy: #1E293B;
  }

  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'DM Sans', sans-serif; background: var(--surface); color: var(--text-primary); overflow-x: hidden; }

  .dash-wrap { padding: 6rem 2rem 3rem; max-width: 1400px; margin: 0 auto; }

  /* ── Header ── */
  .page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
  .page-eyebrow { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.22em; text-transform: uppercase; color: var(--primary); margin-bottom: 0.4rem; display: flex; align-items: center; gap: 0.5rem; }
  .page-eyebrow::before { content: ''; display: inline-block; width: 20px; height: 2px; background: var(--primary); border-radius: 2px; }
  .page-header h1 { font-family: 'Playfair Display', serif; font-size: 1.9rem; font-weight: 700; color: var(--text-primary); line-height: 1.1; }
  .header-chips { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
  .chip { font-size: 0.75rem; background: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
  .chip i { color: var(--primary); }
  .chip.sy  { color: var(--primary); font-weight: 700; border-color: var(--primary-border); background: var(--primary-pale); }
  .chip.dt  { font-variant-numeric: tabular-nums; min-width: 0; }
  #live-clock { font-weight: 600; }

  /* ── Stats Grid ── */
  .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
  .stat-card { background: #fff; border: 1px solid var(--border); border-radius: 14px; padding: 1.35rem 1.5rem; position: relative; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.04); animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both; }
  .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; border-radius: 14px 14px 0 0; }
  .stat-card.card-total::before    { background: var(--primary); }
  .stat-card.card-pending::before  { background: var(--gold-light); }
  .stat-card.card-accepted::before { background: var(--green); }
  .stat-card.card-declined::before { background: var(--red); }
  .stat-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1rem; margin-bottom: 1rem; }
  .card-total    .stat-icon { background: var(--primary-pale); color: var(--primary); }
  .card-pending  .stat-icon { background: var(--gold-pale);    color: var(--gold); }
  .card-accepted .stat-icon { background: var(--green-pale);   color: var(--green); }
  .card-declined .stat-icon { background: var(--red-pale);     color: var(--red); }
  .stat-label { font-size: 0.67rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 0.3rem; }
  .stat-value { font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 700; color: var(--text-primary); line-height: 1; margin-bottom: 0.5rem; }
  .stat-sub { font-size: 0.72rem; color: var(--text-muted); }

  /* ── Charts row ── */
  .charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
  .chart-card { background: #fff; border: 1px solid var(--border); border-radius: 14px; padding: 1.5rem; box-shadow: 0 1px 4px rgba(0,0,0,0.04); animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both 0.1s; display: flex; flex-direction: column; }
  .chart-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; flex-shrink: 0; }
  .chart-card-header h6 { font-family: 'Playfair Display', serif; font-size: 1rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; margin: 0; }
  .chart-card-header h6 i { color: var(--primary); font-size: 0.9rem; }

  /* Donut container — fixed height, chart fills it */
  .chart-container { position: relative; flex: 1; min-height: 0; height: 320px; }
  .chart-container canvas { display: block; }

  /* ── Program bar list ── */
  .section-card { background: #fff; border: 1px solid var(--border); border-radius: 14px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.04); animation: fadeUp 0.5s cubic-bezier(0.22,1,0.36,1) both 0.2s; }
  .section-card-header { padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
  .section-card-header h6 { font-family: 'Playfair Display', serif; font-size: 0.95rem; font-weight: 700; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem; margin: 0; }
  .section-card-header h6 i { color: var(--primary); font-size: 0.85rem; }
  .prog-list { padding: 1rem 1.5rem; }
  .prog-item { margin-bottom: 1.1rem; }
  .prog-item:last-child { margin-bottom: 0; }
  .prog-meta { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem; }
  .prog-name { font-size: 0.8rem; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70%; }
  .prog-count { font-size: 0.78rem; font-weight: 700; color: var(--primary); font-family: 'Playfair Display', serif; }
  .prog-bar-track { height: 7px; background: var(--surface); border-radius: 99px; overflow: hidden; }
  .prog-bar-fill { height: 100%; border-radius: 99px; background: linear-gradient(to right, var(--primary), var(--primary-light)); transition: width 1s cubic-bezier(0.22,1,0.36,1); }

  @keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
  .stat-card:nth-child(1){animation-delay:.00s} .stat-card:nth-child(2){animation-delay:.05s}
  .stat-card:nth-child(3){animation-delay:.10s} .stat-card:nth-child(4){animation-delay:.15s}

  /* ── Responsive ── */
  @media (max-width: 1100px) {
    .stats-grid  { grid-template-columns: repeat(2, 1fr); }
    .charts-row  { grid-template-columns: 1fr; }
  }

  @media (max-width: 768px) {
    .dash-wrap   { padding: 5rem 1rem 2rem; }
    .stats-grid  { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
    .page-header { flex-direction: column; align-items: flex-start; }
    .charts-row  { grid-template-columns: 1fr; gap: 0.75rem; }
    .chart-card  { padding: 1.1rem; }
    .chart-container { height: 260px; }
    .chart-card-header h6 { font-size: 0.9rem; }
    .stat-value  { font-size: 1.6rem; }
    .stat-card   { padding: 1rem 1.1rem; }
    .header-chips { gap: 0.5rem; }
    .chip        { font-size: 0.7rem; padding: 0.4rem 0.75rem; }
  }

  @media (max-width: 400px) {
    .stats-grid  { grid-template-columns: 1fr 1fr; gap: 0.5rem; }
  }
</style>
</head>
<body>
<div class="dash-wrap">

  <!-- Header -->
  <div class="page-header">
    <div>
      <div class="page-eyebrow">NCC Admission Portal</div>
      <h1>Admin Dashboard</h1>
    </div>
    <div class="header-chips">
      <div class="chip sy"><i class="fa fa-graduation-cap"></i>SY <?= $currentSY ? htmlspecialchars($currentSY['school_year']) : 'N/A' ?></div>
      <div class="chip dt"><i class="fa fa-clock"></i><span id="live-clock">--:-- --</span></div>
      <div class="chip dt"><i class="fa fa-calendar"></i><span id="live-date">---</span></div>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="stats-grid">
    <div class="stat-card card-total">
      <div class="stat-icon"><i class="fa fa-users"></i></div>
      <div class="stat-label">Total Applicants</div>
      <div class="stat-value"><?= $totalApplicants ?></div>
      <div class="stat-sub">All registered applicants</div>
    </div>
    <div class="stat-card card-pending">
      <div class="stat-icon"><i class="fa fa-hourglass-half"></i></div>
      <div class="stat-label">Pending</div>
      <div class="stat-value"><?= $totalPending ?></div>
      <div class="stat-sub">Awaiting review</div>
    </div>
    <div class="stat-card card-accepted">
      <div class="stat-icon"><i class="fa fa-check-circle"></i></div>
      <div class="stat-label">Approved</div>
      <div class="stat-value"><?= $totalAccepted ?></div>
      <div class="stat-sub">Applications approved</div>
    </div>
    <div class="stat-card card-declined">
      <div class="stat-icon"><i class="fa fa-times-circle"></i></div>
      <div class="stat-label">Rejected</div>
      <div class="stat-value"><?= $totalDeclined ?></div>
      <div class="stat-sub">Applications rejected</div>
    </div>
  </div>

  <!-- Charts -->
  <div class="charts-row">

    <!-- Donut Chart -->
    <div class="chart-card">
      <div class="chart-card-header">
        <h6><i class="fa fa-chart-pie"></i> Application Status Breakdown</h6>
      </div>
      <div class="chart-container">
        <canvas id="statusChart"></canvas>
      </div>
    </div>

    <!-- Applicants per Program -->
    <div class="section-card">
      <div class="section-card-header">
        <h6><i class="fa fa-graduation-cap"></i> Applicants per Program</h6>
        <span style="font-size:0.72rem;color:var(--text-muted);"><?= $totalApplicants ?> total</span>
      </div>
      <div class="prog-list">
        <?php
        usort($programStats, fn($a, $b) => $b['total'] - $a['total']);
        $maxProg = max(array_column($programStats, 'total') ?: [1]);
        foreach ($programStats as $ps):
          $pct = $maxProg > 0 ? round(($ps['total'] / $maxProg) * 100) : 0;
        ?>
        <div class="prog-item">
          <div class="prog-meta">
            <span class="prog-name" title="<?= htmlspecialchars($ps['name']) ?>"><?= htmlspecialchars($ps['name']) ?></span>
            <span class="prog-count"><?= $ps['total'] ?></span>
          </div>
          <div class="prog-bar-track">
            <div class="prog-bar-fill" style="width:<?= $pct ?>%;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($programStats)): ?>
          <div style="text-align:center;color:var(--text-muted);padding:2rem;font-size:0.85rem;">No program data.</div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /.charts-row -->

</div><!-- /.dash-wrap -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
/* ── Live Philippine Time (UTC+8) ── */
function updateClock() {
  const now = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Manila' }));

  const h   = now.getHours();
  const m   = String(now.getMinutes()).padStart(2, '0');
  const s   = String(now.getSeconds()).padStart(2, '0');
  const ampm = h >= 12 ? 'PM' : 'AM';
  const h12  = h % 12 || 12;
  document.getElementById('live-clock').textContent = `${h12}:${m}:${s} ${ampm}`;

  const days   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  document.getElementById('live-date').textContent =
    `${days[now.getDay()]}, ${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()}`;
}
updateClock();
setInterval(updateClock, 1000);

/* ── Donut Chart ── */
const statusCtx = document.getElementById('statusChart').getContext('2d');

new Chart(statusCtx, {
  type: 'doughnut',
  data: {
    labels: ['Pending', 'Approved', 'Rejected'],
    datasets: [{
      data: [<?= (int)$totalPending ?>, <?= (int)$totalAccepted ?>, <?= (int)$totalDeclined ?>],
      backgroundColor: ['#FEF3C7', '#D1FAE5', '#FEE2E2'],
      borderColor:     ['#F59E0B', '#059669', '#DC2626'],
      borderWidth: 2,
      hoverOffset: 8
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '60%',
    layout: { padding: 10 },
    plugins: {
      legend: {
        position: 'bottom',
        onClick: () => {}, // disable click-to-hide segments
        labels: {
          font: { family: 'DM Sans', size: 11 },
          padding: 14,
          boxWidth: 12
        }
      },
      tooltip: {
        callbacks: {
          label: function(context) {
            const total = context.dataset.data.reduce((a, b) => a + b, 0);
            const pct   = total > 0 ? Math.round((context.parsed / total) * 100) : 0;
            return ` ${context.label}: ${context.parsed} (${pct}%)`;
          }
        }
      }
    }
  }
});
</script>
    <?php include '../layout/footer.php'?>
</body>
</html>