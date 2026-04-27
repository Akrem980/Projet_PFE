<?php
session_start();
require_once '../config.php';
checkAuth();

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['user_role'];

// Load user data
$u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'"));

$first_name = $u['first_name'] ?? '';
$last_name  = $u['last_name']  ?? '';
$email      = $u['email']      ?? '';
$phone      = $u['phone']      ?? '';
$city       = '';

if ($role === 'patient') {
    $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM patients WHERE user_id='$user_id'"));
} elseif ($role === 'doctor') {
    $p = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT d.*, s.name AS specialty FROM doctors d JOIN specialties s ON d.specialty_id = s.id WHERE d.user_id='$user_id'"));
    $city = $p['city'] ?? '';
}

$displayName = htmlspecialchars($first_name . ' ' . $last_name);
$initials    = strtoupper(substr($first_name,0,1) . substr($last_name,0,1));

// Dashboard link based on role
$dashLink = $role === 'doctor' ? 'dashboard_doctor.php' : ($role === 'admin' ? 'dashboard_admin.php' : 'dashboard_patient.php');

$msg  = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$type = isset($_GET['type'])    ? $_GET['type'] : 'info';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Settings – HealthCare Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet"/>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        :root{--bg:#f4f6f9;--white:#fff;--blue:#1a56db;--blue-light:#eff4ff;--green:#10b981;--red:#ef4444;--border:#e5e7eb;--text-primary:#1a1f36;--text-secondary:#6b7280;--shadow:0 1px 3px rgba(0,0,0,.07);--radius:12px;--nav-h:60px;--sidebar-w:240px;}
        body{font-family:'DM Sans',sans-serif;background:var(--bg);color:var(--text-primary);min-height:100vh;}
        header{position:fixed;top:0;left:0;right:0;height:var(--nav-h);background:var(--white);border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;padding:0 24px;z-index:100;}
        .logo{display:flex;align-items:center;gap:10px;}
        .logo-icon{width:36px;height:36px;background:var(--blue);border-radius:10px;display:flex;align-items:center;justify-content:center;}
        .logo-icon svg{width:20px;height:20px;fill:white;}
        .logo-text h1{font-size:15px;font-weight:600;}
        .logo-text p{font-size:11px;color:var(--text-secondary);}
        .header-right{display:flex;align-items:center;gap:10px;}
        .avatar{width:36px;height:36px;background:var(--blue);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;color:white;}
        .user-meta .name{font-size:14px;font-weight:600;}
        .user-meta .role{font-size:12px;color:var(--text-secondary);}
        .layout{display:flex;padding-top:var(--nav-h);min-height:100vh;}
        aside{position:fixed;top:var(--nav-h);left:0;width:var(--sidebar-w);height:calc(100vh - var(--nav-h));background:var(--white);border-right:1px solid var(--border);padding:16px 12px;}
        nav ul{list-style:none;display:flex;flex-direction:column;gap:4px;}
        nav a{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500;color:var(--text-secondary);transition:background .15s,color .15s;}
        nav a:hover{background:var(--bg);color:var(--text-primary);}
        nav a.active{background:var(--blue-light);color:var(--blue);}
        nav a svg{width:18px;height:18px;flex-shrink:0;}
        main{margin-left:var(--sidebar-w);flex:1;padding:32px 36px;}
        .content{max-width:720px;}
        .page-header{margin-bottom:28px;}
        .page-header h2{font-size:24px;font-weight:600;margin-bottom:4px;}
        .page-header p{font-size:14px;color:var(--text-secondary);}
        .card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:28px;margin-bottom:20px;box-shadow:var(--shadow);}
        .card-header{display:flex;align-items:center;gap:10px;margin-bottom:6px;}
        .card-header h3{font-size:16px;font-weight:600;}
        .card-header svg{color:var(--text-primary);}
        .card-desc{font-size:13px;color:var(--text-secondary);margin-bottom:22px;}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
        .form-group{display:flex;flex-direction:column;gap:6px;}
        .form-group label{font-size:13px;font-weight:500;color:var(--text-primary);}
        .form-group input,.form-group select{padding:10px 14px;background:#f9fafb;border:1px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;color:var(--text-primary);outline:none;transition:border .15s;}
        .form-group input:focus,.form-group select:focus{border-color:var(--blue);background:white;}
        .form-group input[readonly]{background:#f3f4f6;cursor:not-allowed;}
        .full{grid-column:1/-1;}
        .btn{display:inline-flex;align-items:center;gap:7px;padding:10px 22px;background:var(--blue);color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;margin-top:4px;}
        .btn:hover{background:#1446c0;}
        .flash{padding:12px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;font-weight:500;}
        .flash.success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
        .flash.error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
        .notif-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--border);}
        .notif-row:last-child{border-bottom:none;padding-bottom:0;}
        .notif-row:first-child{padding-top:0;}
        .notif-info h4{font-size:14px;font-weight:500;}
        .notif-info p{font-size:12px;color:var(--text-secondary);margin-top:2px;}
        .toggle{position:relative;width:44px;height:24px;flex-shrink:0;}
        .toggle input{opacity:0;width:0;height:0;}
        .toggle-track{position:absolute;inset:0;background:#d1d5db;border-radius:24px;cursor:pointer;transition:background .2s;}
        .toggle input:checked + .toggle-track{background:var(--blue);}
        .toggle-track::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;background:white;border-radius:50%;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.25);}
        .toggle input:checked + .toggle-track::after{transform:translateX(20px);}
        @media(max-width:640px){.form-row{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<header>
    <div class="logo">
        <div class="logo-icon"><svg viewBox="0 0 24 24"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></div>
        <div class="logo-text"><h1>HealthCare Portal</h1><p>Medical Appointment Management</p></div>
    </div>
    <div class="header-right">
        <div class="avatar"><?= $initials ?></div>
        <div class="user-meta"><div class="name"><?= $displayName ?></div><div class="role"><?= ucfirst($role) ?></div></div>
    </div>
</header>

<div class="layout">
    <aside>
        <nav><ul>
            <li><a href="<?= $dashLink ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard
            </a></li>
            <?php if ($role === 'patient'): ?>
            <li><a href="find_doctor.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Find Doctors
            </a></li>
            <?php endif; ?>
            <li><a href="settings.php" class="active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Settings
            </a></li>
            <li><a href="../Process/logout.php" style="margin-top:8px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout
            </a></li>
        </ul></nav>
    </aside>

    <main>
        <div class="content">
            <?php if ($msg): ?><div class="flash <?= htmlspecialchars($type) ?>"><?= $msg ?></div><?php endif; ?>

            <div class="page-header">
                <h2>Settings</h2>
                <p>Manage your account settings and preferences</p>
            </div>

            <!-- Profile Information -->
            <div class="card">
                <div class="card-header">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <h3>Profile Information</h3>
                </div>
                <p class="card-desc">Update your personal information</p>
                <form method="POST" action="../Process/update_settings.php">
                    <input type="hidden" name="action" value="update_profile"/>
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($first_name) ?>" required/>
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($last_name) ?>" required/>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" value="<?= htmlspecialchars($email) ?>" readonly/>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($phone) ?>"/>
                        </div>
                    </div>
                    <?php if ($role === 'doctor'): ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" name="city" value="<?= htmlspecialchars($city) ?>"/>
                        </div>
                        <div class="form-group">
                            <label>Specialty</label>
                            <input type="text" value="<?= isset($p['specialty']) ? htmlspecialchars($p['specialty']) : '' ?>" readonly/>
                        </div>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Save Changes
                    </button>
                </form>
            </div>

            <!-- Notifications -->
            <div class="card">
                <div class="card-header">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <h3>Notifications</h3>
                </div>
                <p class="card-desc">Manage how you receive notifications</p>
                <div class="notif-row">
                    <div class="notif-info"><h4>Email Notifications</h4><p>Receive appointment reminders via email</p></div>
                    <label class="toggle"><input type="checkbox" checked/><span class="toggle-track"></span></label>
                </div>
                <div class="notif-row">
                    <div class="notif-info"><h4>SMS Notifications</h4><p>Get text message alerts</p></div>
                    <label class="toggle"><input type="checkbox" checked/><span class="toggle-track"></span></label>
                </div>
                <div class="notif-row">
                    <div class="notif-info"><h4>Push Notifications</h4><p>Browser push notifications</p></div>
                    <label class="toggle"><input type="checkbox"/><span class="toggle-track"></span></label>
                </div>
            </div>

            <!-- Security / Change Password -->
            <div class="card">
                <div class="card-header">
                    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    <h3>Security</h3>
                </div>
                <p class="card-desc">Change your password</p>
                <form method="POST" action="../Process/update_settings.php">
                    <input type="hidden" name="action" value="update_password"/>
                    <div class="form-row">
                        <div class="form-group full">
                            <label>Current Password</label>
                            <input type="password" name="current_password" required/>
                        </div>
                        <div class="form-group">
                            <label>New Password</label>
                            <input type="password" name="new_password" minlength="6" required/>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" minlength="6" required/>
                        </div>
                    </div>
                    <button type="submit" class="btn">Update Password</button>
                </form>
            </div>
        </div>
    </main>
</div>
</body>
</html>
