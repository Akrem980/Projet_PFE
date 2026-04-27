<?php
session_start();
require_once '../config.php';
checkAuth('admin');

$user_id = $_SESSION['user_id'];
$u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT first_name, last_name FROM users WHERE id='$user_id'"));
$displayName = $u ? htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) : 'Admin';
$initials    = $u ? strtoupper(substr($u['first_name'],0,1).substr($u['last_name'],0,1)) : 'AD';

// Stats
$total_users    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM users"))['c'];
$total_patients = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM patients"))['c'];
$total_doctors  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM doctors"))['c'];
$total_appts    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM appointments"))['c'];
$sched_appts    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM appointments WHERE status='scheduled'"))['c'];

// List users
$users_res = mysqli_query($conn, "SELECT id, first_name, last_name, email, role, is_active, created_at FROM users ORDER BY created_at DESC");
$users = [];
while ($row = mysqli_fetch_assoc($users_res)) $users[] = $row;

// Recent appointments
$recent_res = mysqli_query($conn,
    "SELECT a.id, a.appointment_date, a.start_time, a.status,
            p.first_name AS pat_fn, p.last_name AS pat_ln,
            d.first_name AS doc_fn, d.last_name AS doc_ln
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     JOIN doctors d ON a.doctor_id = d.id
     ORDER BY a.created_at DESC LIMIT 20");
$recent_appts = [];
while ($row = mysqli_fetch_assoc($recent_res)) $recent_appts[] = $row;

// Handle toggle active action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user'])) {
    $tid    = intval($_POST['toggle_user']);
    $active = intval($_POST['is_active']);
    $new    = $active ? 0 : 1;
    mysqli_query($conn, "UPDATE users SET is_active=$new WHERE id=$tid");
    header("Location: dashboard_admin.php?message=User+status+updated&type=success");
    exit();
}

$msg  = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$type = isset($_GET['type'])    ? $_GET['type'] : 'info';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Admin Dashboard – HealthCare Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet"/>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        :root{--bg:#f4f6f9;--white:#fff;--blue:#1a56db;--blue-light:#eff4ff;--green:#10b981;--red:#ef4444;--orange:#f59e0b;--border:#e5e7eb;--text-primary:#1a1f36;--text-secondary:#6b7280;--shadow:0 1px 3px rgba(0,0,0,.07);--radius:12px;--nav-h:60px;--sidebar-w:240px;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text-primary);min-height:100vh;}
        header{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:100;}
        .logo{display:flex;align-items:center;gap:10px;}
        .logo-icon{width:36px;height:36px;background:#7c3aed;border-radius:10px;display:flex;align-items:center;justify-content:center;}
        .logo-icon svg{width:20px;height:20px;fill:white;}
        .logo-text h1{font-size:15px;font-weight:600;}
        .logo-text p{font-size:11px;color:var(--text-secondary);}
        .header-right{display:flex;align-items:center;gap:12px;}
        .avatar{width:36px;height:36px;background:#7c3aed;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:white;}
        .user-meta .name{font-size:14px;font-weight:600;}
        .user-meta .role{font-size:12px;color:var(--text-secondary);}
        .layout{display:flex;padding-top:var(--nav-h);min-height:100vh;}
        aside{position:fixed;top:var(--nav-h);left:0;width:var(--sidebar-w);height:calc(100vh - var(--nav-h));background:var(--white);border-right:1px solid var(--border);padding:16px 12px;overflow-y:auto;}
        nav ul{list-style:none;display:flex;flex-direction:column;gap:4px;}
        nav a{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;color:var(--text-secondary);transition:background .15s,color .15s;}
        nav a:hover{background:var(--bg);color:var(--text-primary);}
        nav a.active{background:#f3e8ff;color:#7c3aed;}
        nav a svg{width:18px;height:18px;flex-shrink:0;}
        main{margin-left:var(--sidebar-w);flex:1;padding:32px 36px;}
        .page-header{margin-bottom:28px;}
        .page-header h2{font-size:24px;font-weight:600;margin-bottom:4px;}
        .page-header p{font-size:14px;color:var(--text-secondary);}
        .stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:28px;}
        .stat-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:18px 20px;box-shadow:var(--shadow);}
        .stat-card .label{font-size:11px;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;}
        .stat-card .value{font-size:26px;font-weight:700;}
        .card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden;margin-bottom:28px;}
        .card-header{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
        .card-header h3{font-size:16px;font-weight:600;}
        table{width:100%;border-collapse:collapse;}
        th{padding:12px 16px;text-align:left;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:var(--text-secondary);border-bottom:1px solid var(--border);background:#fafbff;}
        td{padding:14px 16px;font-size:14px;border-bottom:1px solid var(--border);}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:#fafbff;}
        .role-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
        .role-patient{background:#dbeafe;color:#1e40af;}
        .role-doctor{background:#d1fae5;color:#065f46;}
        .role-admin{background:#ede9fe;color:#5b21b6;}
        .active-badge{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;}
        .active-1{background:#d1fae5;color:#065f46;}
        .active-0{background:#fee2e2;color:#991b1b;}
        .status-badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;}
        .status-scheduled{background:#dbeafe;color:#1e40af;}
        .status-completed{background:#d1fae5;color:#065f46;}
        .status-cancelled{background:#fee2e2;color:#991b1b;}
        .btn-toggle{padding:5px 12px;border-radius:6px;border:1px solid var(--border);font-size:12px;font-weight:600;font-family:inherit;cursor:pointer;background:var(--white);}
        .btn-toggle:hover{background:var(--bg);}
        .flash{padding:12px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;font-weight:500;}
        .flash.success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
        .flash.error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
        .tabs{display:flex;gap:4px;margin-bottom:20px;background:var(--white);border:1px solid var(--border);border-radius:10px;padding:4px;width:fit-content;}
        .tab-btn{padding:8px 18px;border-radius:8px;border:none;background:none;font-size:14px;font-weight:500;font-family:inherit;cursor:pointer;color:var(--text-secondary);}
        .tab-btn.active{background:#7c3aed;color:white;}
        .tab-content{display:none;}
        .tab-content.active{display:block;}
    </style>
</head>
<body>
<header>
    <div class="logo">
        <div class="logo-icon"><svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></div>
        <div class="logo-text"><h1>HealthCare Portal</h1><p>Administration</p></div>
    </div>
    <div class="header-right">
        <div class="avatar"><?= $initials ?></div>
        <div class="user-meta"><div class="name"><?= $displayName ?></div><div class="role">Administrator</div></div>
    </div>
</header>

<div class="layout">
    <aside>
        <nav><ul>
            <li><a href="dashboard_admin.php" class="active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard
            </a></li>
            <li><a href="../Process/logout.php" style="margin-top:8px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout
            </a></li>
        </ul></nav>
    </aside>

    <main>
        <?php if ($msg): ?><div class="flash <?= htmlspecialchars($type) ?>"><?= $msg ?></div><?php endif; ?>

        <div class="page-header">
            <h2>Admin Dashboard</h2>
            <p>System overview and user management</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card"><div class="label">Total Users</div><div class="value" style="color:#7c3aed"><?= $total_users ?></div></div>
            <div class="stat-card"><div class="label">Patients</div><div class="value" style="color:var(--blue)"><?= $total_patients ?></div></div>
            <div class="stat-card"><div class="label">Doctors</div><div class="value" style="color:var(--green)"><?= $total_doctors ?></div></div>
            <div class="stat-card"><div class="label">Total Appts</div><div class="value" style="color:var(--orange)"><?= $total_appts ?></div></div>
            <div class="stat-card"><div class="label">Scheduled</div><div class="value" style="color:var(--blue)"><?= $sched_appts ?></div></div>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('users',this)">Users (<?= $total_users ?>)</button>
            <button class="tab-btn" onclick="switchTab('appts',this)">Appointments (<?= $total_appts ?>)</button>
        </div>

        <div id="tab-users" class="tab-content active">
            <div class="card">
                <div class="card-header"><h3>All Users</h3></div>
                <table>
                    <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['first_name'] . ' ' . $u['last_name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="role-badge role-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                        <td><span class="active-badge active-<?= $u['is_active'] ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="toggle_user" value="<?= $u['id'] ?>">
                                <input type="hidden" name="is_active" value="<?= $u['is_active'] ?>">
                                <button type="submit" class="btn-toggle"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="tab-appts" class="tab-content">
            <div class="card">
                <div class="card-header"><h3>Recent Appointments</h3></div>
                <table>
                    <thead><tr><th>#</th><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_appts as $a): ?>
                    <tr>
                        <td><?= $a['id'] ?></td>
                        <td><?= htmlspecialchars($a['pat_fn'] . ' ' . $a['pat_ln']) ?></td>
                        <td>Dr. <?= htmlspecialchars($a['doc_fn'] . ' ' . $a['doc_ln']) ?></td>
                        <td><?= date('M j, Y', strtotime($a['appointment_date'])) ?></td>
                        <td><?= substr($a['start_time'],0,5) ?></td>
                        <td><span class="status-badge status-<?= $a['status'] ?>"><?= ucfirst($a['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
<script>
function switchTab(name, el) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    el.classList.add('active');
}
</script>
</body>
</html>
