<?php
session_start();
require_once '../config.php';
checkAuth('patient');

$user_id = $_SESSION['user_id'];

// Get patient info
$stmt = mysqli_prepare($conn, "SELECT p.id, p.first_name, p.last_name FROM patients p WHERE p.user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$patient = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$patient) {
    // Patient row missing – create it from users table
    $u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'"));
    if ($u) {
        $fn = mysqli_real_escape_string($conn, $u['first_name']);
        $ln = mysqli_real_escape_string($conn, $u['last_name']);
        $em = mysqli_real_escape_string($conn, $u['email']);
        $ph = mysqli_real_escape_string($conn, $u['phone']);
        mysqli_query($conn, "INSERT INTO patients (user_id, first_name, last_name, email, phone) VALUES ('$user_id','$fn','$ln','$em','$ph')");
        $patient = ['id' => mysqli_insert_id($conn), 'first_name' => $u['first_name'], 'last_name' => $u['last_name']];
    }
}

$patient_id  = $patient['id'] ?? 0;
$displayName = $patient ? htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) : 'Patient';
$initials    = $patient ? strtoupper(substr($patient['first_name'],0,1) . substr($patient['last_name'],0,1)) : '?';

// Get upcoming appointments
$appts_res = mysqli_query($conn,
    "SELECT a.*, d.first_name AS doc_fn, d.last_name AS doc_ln, s.name AS specialty
     FROM appointments a
     JOIN doctors d ON a.doctor_id = d.id
     JOIN specialties s ON d.specialty_id = s.id
     WHERE a.patient_id = '$patient_id' AND a.status = 'scheduled' AND a.appointment_date >= CURDATE()
     ORDER BY a.appointment_date ASC, a.start_time ASC
     LIMIT 10");
$appointments = [];
while ($row = mysqli_fetch_assoc($appts_res)) $appointments[] = $row;

// Stats
$total_res    = mysqli_query($conn, "SELECT COUNT(*) as c FROM appointments WHERE patient_id='$patient_id'");
$total        = mysqli_fetch_assoc($total_res)['c'] ?? 0;
$upcoming_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM appointments WHERE patient_id='$patient_id' AND status='scheduled' AND appointment_date >= CURDATE()");
$upcoming_cnt = mysqli_fetch_assoc($upcoming_res)['c'] ?? 0;
$completed_res= mysqli_query($conn, "SELECT COUNT(*) as c FROM appointments WHERE patient_id='$patient_id' AND status='completed'");
$completed_cnt= mysqli_fetch_assoc($completed_res)['c'] ?? 0;

// Message from redirect
$msg  = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$type = isset($_GET['type'])    ? $_GET['type'] : 'error';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Patient Dashboard – HealthCare Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet"/>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        :root{
            --bg:#f4f6f9;--white:#fff;--blue:#1a56db;--blue-light:#eff4ff;
            --green:#10b981;--red:#ef4444;--orange:#f59e0b;
            --border:#e5e7eb;--text-primary:#1a1f36;--text-secondary:#6b7280;
            --shadow:0 1px 3px rgba(0,0,0,.07);--shadow-md:0 4px 12px rgba(0,0,0,.08);
            --radius:12px;--nav-h:60px;--sidebar-w:240px;
        }
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text-primary);min-height:100vh;}
        header{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:100;}
        .logo{display:flex;align-items:center;gap:10px;}
        .logo-icon{width:36px;height:36px;background:var(--blue);border-radius:10px;display:flex;align-items:center;justify-content:center;}
        .logo-icon svg{width:20px;height:20px;fill:white;}
        .logo-text h1{font-size:15px;font-weight:600;line-height:1.2;}
        .logo-text p{font-size:11px;color:var(--text-secondary);}
        .header-right{display:flex;align-items:center;gap:16px;}
        .notif-btn{position:relative;background:none;border:none;cursor:pointer;padding:6px;color:var(--text-secondary);}
        .notif-btn svg{width:22px;height:22px;display:block;}
        .user-info{display:flex;align-items:center;gap:10px;cursor:pointer;}
        .avatar{width:36px;height:36px;background:var(--blue);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:white;}
        .user-meta .name{font-size:14px;font-weight:600;}
        .user-meta .role{font-size:12px;color:var(--text-secondary);}
        .layout{display:flex;padding-top:var(--nav-h);min-height:100vh;}
        aside{position:fixed;top:var(--nav-h);left:0;width:var(--sidebar-w);height:calc(100vh - var(--nav-h));background:var(--white);border-right:1px solid var(--border);padding:16px 12px;overflow-y:auto;}
        nav ul{list-style:none;display:flex;flex-direction:column;gap:4px;}
        nav a{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;color:var(--text-secondary);transition:background .15s,color .15s;}
        nav a:hover{background:var(--bg);color:var(--text-primary);}
        nav a.active{background:var(--blue-light);color:var(--blue);}
        nav a svg{width:18px;height:18px;flex-shrink:0;}
        main{margin-left:var(--sidebar-w);flex:1;padding:32px 36px;}
        .page-header{margin-bottom:24px;}
        .page-header h2{font-size:24px;font-weight:600;margin-bottom:4px;}
        .page-header p{font-size:14px;color:var(--text-secondary);}
        .stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
        .stat-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px 22px;box-shadow:var(--shadow);}
        .stat-card .label{font-size:13px;color:var(--text-secondary);margin-bottom:6px;}
        .stat-card .value{font-size:28px;font-weight:700;color:var(--blue);}
        .banner{background:var(--white);border:1px solid #c7d9f8;border-radius:var(--radius);padding:22px 28px;display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;box-shadow:var(--shadow);}
        .banner-text h3{font-size:16px;font-weight:600;margin-bottom:4px;}
        .banner-text p{font-size:13px;color:var(--text-secondary);}
        .btn-primary{display:inline-flex;align-items:center;gap:8px;background:var(--blue);color:white;border:none;border-radius:8px;padding:11px 22px;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;text-decoration:none;transition:background .15s;}
        .btn-primary:hover{background:#1446c0;}
        .card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;}
        .card-header{display:flex;align-items:flex-start;justify-content:space-between;padding:22px 24px 16px;}
        .card-header-left h3{font-size:16px;font-weight:600;margin-bottom:2px;}
        .card-header-left p{font-size:13px;color:var(--text-secondary);}
        .appt-item{display:flex;align-items:center;gap:16px;padding:18px 24px;border-top:1px solid var(--border);transition:background .12s;}
        .appt-item:hover{background:#fafbff;}
        .date-badge{min-width:52px;background:var(--blue-light);border-radius:10px;text-align:center;padding:8px 6px;}
        .date-badge .month{font-size:10px;font-weight:600;color:var(--blue);text-transform:uppercase;}
        .date-badge .day{font-size:22px;font-weight:700;color:var(--blue);line-height:1.1;}
        .appt-info{flex:1;}
        .appt-info .doctor-name{font-size:15px;font-weight:600;margin-bottom:2px;}
        .appt-info .specialty{font-size:13px;color:var(--text-secondary);margin-bottom:8px;}
        .appt-meta{display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
        .meta-item{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-secondary);}
        .meta-item svg{width:14px;height:14px;flex-shrink:0;}
        .tag{border-radius:20px;padding:4px 12px;font-size:12px;font-weight:600;}
        .tag-green{background:#d1fae5;color:#065f46;}
        .tag-orange{background:#fef3c7;color:#92400e;}
        .tag-red{background:#fee2e2;color:#991b1b;}
        .empty-state{padding:40px 24px;text-align:center;color:var(--text-secondary);}
        .empty-state svg{width:48px;height:48px;margin:0 auto 12px;opacity:.3;}
        .flash{padding:12px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;font-weight:500;}
        .flash.success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
        .flash.error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
        .logout-btn{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;color:var(--text-secondary);transition:background .15s,color .15s;width:100%;background:none;border:none;cursor:pointer;margin-top:8px;}
        .logout-btn:hover{background:#fee2e2;color:var(--red);}
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
            <div class="user-meta"><div class="name"><?= $displayName ?></div><div class="role">Patient</div></div>
        </div>
    </div>
</header>

<div class="layout">
    <aside>
        <nav>
            <ul>
                <li><a href="dashboard_patient.php" class="active">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard
                </a></li>
                <li><a href="find_doctor.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Find Doctors
                </a></li>
                <li><a href="settings.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Settings
                </a></li>
                <li><a href="../Process/logout.php" class="logout-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout
                </a></li>
            </ul>
        </nav>
    </aside>

    <main>
        <?php if ($msg): ?>
        <div class="flash <?= $type ?>"><?= $msg ?></div>
        <?php endif; ?>

        <div class="page-header">
            <h2>Welcome back, <?= $displayName ?>!</h2>
            <p>Manage your health appointments and medical care</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="label">Total Appointments</div><div class="value"><?= $total ?></div></div>
            <div class="stat-card"><div class="label">Upcoming</div><div class="value" style="color:var(--orange)"><?= $upcoming_cnt ?></div></div>
            <div class="stat-card"><div class="label">Completed</div><div class="value" style="color:var(--green)"><?= $completed_cnt ?></div></div>
        </div>

        <div class="banner">
            <div class="banner-text">
                <h3>Need to see a doctor?</h3>
                <p>Search for specialists and book your appointment today</p>
            </div>
            <a href="find_doctor.php" class="btn-primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Book Appointment
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-header-left">
                    <h3>Upcoming Appointments</h3>
                    <p>Your scheduled medical visits</p>
                </div>
            </div>

            <?php if (empty($appointments)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <p>No upcoming appointments. <a href="find_doctor.php">Book one now</a>.</p>
            </div>
            <?php else: ?>
            <?php foreach ($appointments as $a):
                $d = new DateTime($a['appointment_date']);
            ?>
            <div class="appt-item">
                <div class="date-badge">
                    <div class="month"><?= $d->format('M') ?></div>
                    <div class="day"><?= $d->format('j') ?></div>
                </div>
                <div class="appt-info">
                    <div class="doctor-name">Dr. <?= htmlspecialchars($a['doc_fn'] . ' ' . $a['doc_ln']) ?></div>
                    <div class="specialty"><?= htmlspecialchars($a['specialty']) ?></div>
                    <div class="appt-meta">
                        <span class="meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?= substr($a['start_time'],0,5) ?> – <?= substr($a['end_time'],0,5) ?>
                        </span>
                        <span class="meta-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            <?= $d->format('D, M j, Y') ?>
                        </span>
                    </div>
                </div>
                <span class="tag tag-green">Scheduled</span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
