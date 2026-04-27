<?php
session_start();
require_once '../config.php';
checkAuth('doctor');

$user_id = $_SESSION['user_id'];

// Get doctor info
$stmt = mysqli_prepare($conn, "SELECT d.id, d.first_name, d.last_name, d.city, s.name AS specialty FROM doctors d JOIN specialties s ON d.specialty_id = s.id WHERE d.user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$doctor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$doctor) {
    $u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'"));
    $displayName = $u ? htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) : 'Doctor';
    $initials    = $u ? strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) : 'DR';
    $doctor_id   = 0;
} else {
    $displayName = htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']);
    $initials    = strtoupper(substr($doctor['first_name'],0,1).substr($doctor['last_name'],0,1));
    $doctor_id   = $doctor['id'];
}

// Get appointments with patient info
$all_appts = [];
if ($doctor_id) {
    $res = mysqli_query($conn,
        "SELECT a.*, p.first_name AS pat_fn, p.last_name AS pat_ln, u.phone AS pat_phone
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         JOIN users u ON p.user_id = u.id
         WHERE a.doctor_id = '$doctor_id'
         ORDER BY a.appointment_date DESC, a.start_time DESC");
    while ($row = mysqli_fetch_assoc($res)) $all_appts[] = $row;
}

$today_appts    = array_filter($all_appts, fn($a) => $a['appointment_date'] === date('Y-m-d') && $a['status'] === 'scheduled');
$upcoming_appts = array_filter($all_appts, fn($a) => $a['appointment_date'] > date('Y-m-d') && $a['status'] === 'scheduled');
$past_appts     = array_filter($all_appts, fn($a) => $a['status'] === 'completed' || $a['status'] === 'cancelled' || $a['appointment_date'] < date('Y-m-d'));

$total_appts      = count($all_appts);
$scheduled_count  = count(array_filter($all_appts, fn($a) => $a['status'] === 'scheduled'));
$completed_count  = count(array_filter($all_appts, fn($a) => $a['status'] === 'completed'));
$cancelled_count  = count(array_filter($all_appts, fn($a) => $a['status'] === 'cancelled'));

$msg  = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$type = isset($_GET['type'])    ? $_GET['type'] : 'info';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Doctor Dashboard – HealthCare Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet"/>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        :root{
            --bg:#f4f6f9;--white:#fff;--blue:#1a56db;--blue-light:#eff4ff;
            --green:#10b981;--red:#ef4444;--orange:#f59e0b;
            --border:#e5e7eb;--text-primary:#1a1f36;--text-secondary:#6b7280;
            --shadow:0 1px 3px rgba(0,0,0,.07);--radius:12px;
            --nav-h:60px;--sidebar-w:240px;
        }
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text-primary);min-height:100vh;}
        header{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:100;}
        .logo{display:flex;align-items:center;gap:10px;}
        .logo-icon{width:36px;height:36px;background:var(--blue);border-radius:10px;display:flex;align-items:center;justify-content:center;}
        .logo-icon svg{width:20px;height:20px;fill:white;}
        .logo-text h1{font-size:15px;font-weight:600;line-height:1.2;}
        .logo-text p{font-size:11px;color:var(--text-secondary);}
        .header-right{display:flex;align-items:center;gap:16px;}
        .user-info{display:flex;align-items:center;gap:10px;}
        .avatar{width:36px;height:36px;background:var(--green);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:white;}
        .user-meta .name{font-size:14px;font-weight:600;}
        .user-meta .role{font-size:12px;color:var(--text-secondary);}
        .layout{display:flex;padding-top:var(--nav-h);min-height:100vh;}
        aside{position:fixed;top:var(--nav-h);left:0;width:var(--sidebar-w);height:calc(100vh - var(--nav-h));background:var(--white);border-right:1px solid var(--border);padding:16px 12px;overflow-y:auto;}
        nav ul{list-style:none;display:flex;flex-direction:column;gap:4px;}
        nav a{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;color:var(--text-secondary);transition:background .15s,color .15s;}
        nav a:hover{background:var(--bg);color:var(--text-primary);}
        nav a.active{background:var(--blue-light);color:var(--blue);}
        nav a svg{width:18px;height:18px;flex-shrink:0;}
        .logout-link{color:var(--text-secondary);}
        .logout-link:hover{background:#fee2e2 !important;color:var(--red) !important;}
        main{margin-left:var(--sidebar-w);flex:1;padding:32px 36px;}
        .page-header{margin-bottom:24px;}
        .page-header h2{font-size:24px;font-weight:600;margin-bottom:4px;}
        .page-header p{font-size:14px;color:var(--text-secondary);}
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
        .stat-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;box-shadow:var(--shadow);}
        .stat-card .label{font-size:12px;color:var(--text-secondary);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;}
        .stat-card .value{font-size:28px;font-weight:700;}
        .stat-card .value.blue{color:var(--blue);}
        .stat-card .value.green{color:var(--green);}
        .stat-card .value.orange{color:var(--orange);}
        .stat-card .value.red{color:var(--red);}
        .tabs{display:flex;gap:4px;margin-bottom:20px;background:var(--white);border:1px solid var(--border);border-radius:10px;padding:4px;width:fit-content;}
        .tab-btn{padding:8px 18px;border-radius:8px;border:none;background:none;font-size:14px;font-weight:500;font-family:inherit;cursor:pointer;color:var(--text-secondary);transition:background .15s,color .15s;}
        .tab-btn.active{background:var(--blue);color:white;}
        .tab-content{display:none;}
        .tab-content.active{display:block;}
        .card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
        .card-header{padding:20px 24px;border-bottom:1px solid var(--border);}
        .card-header h3{font-size:16px;font-weight:600;}
        .appt-row{display:flex;align-items:center;gap:16px;padding:16px 24px;border-bottom:1px solid var(--border);transition:background .12s;}
        .appt-row:last-child{border-bottom:none;}
        .appt-row:hover{background:#fafbff;}
        .date-col{min-width:90px;font-size:13px;color:var(--text-secondary);}
        .date-col strong{display:block;font-size:15px;font-weight:600;color:var(--text-primary);}
        .patient-col{flex:1;}
        .patient-name{font-size:15px;font-weight:600;margin-bottom:2px;}
        .patient-phone{font-size:12px;color:var(--text-secondary);}
        .time-col{font-size:13px;color:var(--text-secondary);min-width:110px;}
        .status-badge{padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;}
        .status-scheduled{background:#dbeafe;color:#1e40af;}
        .status-completed{background:#d1fae5;color:#065f46;}
        .status-cancelled{background:#fee2e2;color:#991b1b;}
        .actions{display:flex;gap:8px;align-items:center;}
        .btn-sm{padding:6px 14px;border-radius:7px;border:none;font-size:13px;font-weight:600;font-family:inherit;cursor:pointer;transition:background .15s;}
        .btn-accept{background:#d1fae5;color:#065f46;}
        .btn-accept:hover{background:#a7f3d0;}
        .btn-cancel{background:#fee2e2;color:#991b1b;}
        .btn-cancel:hover{background:#fecaca;}
        .btn-complete{background:#e0e7ff;color:#3730a3;}
        .btn-complete:hover{background:#c7d2fe;}
        .empty-state{padding:40px 24px;text-align:center;color:var(--text-secondary);font-size:14px;}
        .flash{padding:12px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;font-weight:500;}
        .flash.success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
        .flash.error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
        .flash.info{background:#dbeafe;color:#1e40af;border:1px solid #93c5fd;}
        .doctor-badge{display:inline-flex;align-items:center;gap:6px;background:#d1fae5;color:#065f46;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;margin-bottom:8px;}
    </style>
</head>
<body>
<header>
    <div class="logo">
        <div class="logo-icon"><svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></div>
        <div class="logo-text"><h1>HealthCare Portal</h1><p>Medical Appointment Management</p></div>
    </div>
    <div class="header-right">
        <div class="user-info">
            <div class="avatar"><?= $initials ?></div>
            <div class="user-meta">
                <div class="name">Dr. <?= $displayName ?></div>
                <div class="role"><?= $doctor ? htmlspecialchars($doctor['specialty']) : 'Doctor' ?></div>
            </div>
        </div>
    </div>
</header>

<div class="layout">
    <aside>
        <nav>
            <ul>
                <li><a href="dashboard_doctor.php" class="active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard
                </a></li>
                <li><a href="settings.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Settings
                </a></li>
                <li><a href="../Process/logout.php" class="logout-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout
                </a></li>
            </ul>
        </nav>
    </aside>

    <main>
        <?php if ($msg): ?>
        <div class="flash <?= htmlspecialchars($type) ?>"><?= $msg ?></div>
        <?php endif; ?>

        <div class="page-header">
            <div class="doctor-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                <?= $doctor ? htmlspecialchars($doctor['specialty']) : 'Doctor' ?>
            </div>
            <h2>Doctor Dashboard</h2>
            <p>Welcome, Dr. <?= $displayName ?> <?= $doctor && $doctor['city'] ? '— ' . htmlspecialchars($doctor['city']) : '' ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="label">Total</div><div class="value blue"><?= $total_appts ?></div></div>
            <div class="stat-card"><div class="label">Scheduled</div><div class="value orange"><?= $scheduled_count ?></div></div>
            <div class="stat-card"><div class="label">Completed</div><div class="value green"><?= $completed_count ?></div></div>
            <div class="stat-card"><div class="label">Cancelled</div><div class="value red"><?= $cancelled_count ?></div></div>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('today')">Today (<?= count($today_appts) ?>)</button>
            <button class="tab-btn" onclick="switchTab('upcoming')">Upcoming (<?= count($upcoming_appts) ?>)</button>
            <button class="tab-btn" onclick="switchTab('all')">All Appointments (<?= $total_appts ?>)</button>
        </div>

        <!-- Today Tab -->
        <div id="tab-today" class="tab-content active">
            <div class="card">
                <div class="card-header"><h3>Today's Appointments — <?= date('l, F j, Y') ?></h3></div>
                <?php if (empty($today_appts)): ?>
                <div class="empty-state">No appointments scheduled for today.</div>
                <?php else: foreach ($today_appts as $a): ?>
                <div class="appt-row">
                    <div class="date-col">
                        <strong><?= substr($a['start_time'],0,5) ?></strong>
                        <?= substr($a['end_time'],0,5) ?>
                    </div>
                    <div class="patient-col">
                        <div class="patient-name"><?= htmlspecialchars($a['pat_fn'] . ' ' . $a['pat_ln']) ?></div>
                        <div class="patient-phone"><?= htmlspecialchars($a['pat_phone']) ?></div>
                    </div>
                    <span class="status-badge status-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
                    <div class="actions">
                        <form method="POST" action="../Process/manage_appointment.php" style="display:inline">
                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="action" value="completed">
                            <button type="submit" class="btn-sm btn-complete">Complete</button>
                        </form>
                        <form method="POST" action="../Process/manage_appointment.php" style="display:inline">
                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="action" value="cancelled">
                            <button type="submit" class="btn-sm btn-cancel" onclick="return confirm('Cancel this appointment?')">Cancel</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Upcoming Tab -->
        <div id="tab-upcoming" class="tab-content">
            <div class="card">
                <div class="card-header"><h3>Upcoming Appointments</h3></div>
                <?php if (empty($upcoming_appts)): ?>
                <div class="empty-state">No upcoming appointments.</div>
                <?php else: foreach ($upcoming_appts as $a):
                    $d = new DateTime($a['appointment_date']);
                ?>
                <div class="appt-row">
                    <div class="date-col">
                        <strong><?= $d->format('M j') ?></strong>
                        <?= $d->format('Y') ?>
                    </div>
                    <div class="patient-col">
                        <div class="patient-name"><?= htmlspecialchars($a['pat_fn'] . ' ' . $a['pat_ln']) ?></div>
                        <div class="patient-phone"><?= substr($a['start_time'],0,5) ?> – <?= substr($a['end_time'],0,5) ?></div>
                    </div>
                    <span class="status-badge status-scheduled">Scheduled</span>
                    <div class="actions">
                        <form method="POST" action="../Process/manage_appointment.php" style="display:inline">
                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="action" value="cancelled">
                            <button type="submit" class="btn-sm btn-cancel" onclick="return confirm('Cancel this appointment?')">Cancel</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- All Tab -->
        <div id="tab-all" class="tab-content">
            <div class="card">
                <div class="card-header"><h3>All Appointments</h3></div>
                <?php if (empty($all_appts)): ?>
                <div class="empty-state">No appointments found.</div>
                <?php else: foreach ($all_appts as $a):
                    $d = new DateTime($a['appointment_date']);
                ?>
                <div class="appt-row">
                    <div class="date-col">
                        <strong><?= $d->format('M j') ?></strong>
                        <?= $d->format('Y') ?>
                    </div>
                    <div class="patient-col">
                        <div class="patient-name"><?= htmlspecialchars($a['pat_fn'] . ' ' . $a['pat_ln']) ?></div>
                        <div class="patient-phone"><?= substr($a['start_time'],0,5) ?> – <?= substr($a['end_time'],0,5) ?></div>
                    </div>
                    <span class="status-badge status-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span>
                    <div class="actions">
                        <?php if ($a['status'] === 'scheduled'): ?>
                        <form method="POST" action="../Process/manage_appointment.php" style="display:inline">
                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="action" value="completed">
                            <button type="submit" class="btn-sm btn-complete">Complete</button>
                        </form>
                        <form method="POST" action="../Process/manage_appointment.php" style="display:inline">
                            <input type="hidden" name="appointment_id" value="<?= $a['id'] ?>">
                            <input type="hidden" name="action" value="cancelled">
                            <button type="submit" class="btn-sm btn-cancel" onclick="return confirm('Cancel this appointment?')">Cancel</button>
                        </form>
                        <?php else: ?>
                        <span style="font-size:12px;color:var(--text-secondary)">—</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
function switchTab(name) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    event.target.classList.add('active');
}
</script>
</body>
</html>
