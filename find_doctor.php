<?php
session_start();
require_once '../config.php';
checkAuth('patient');

$user_id = $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT p.id, p.first_name, p.last_name FROM patients p WHERE p.user_id = ?");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$patient = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$patient) {
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

$displayName = $patient ? htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) : 'Patient';
$initials    = $patient ? strtoupper(substr($patient['first_name'],0,1) . substr($patient['last_name'],0,1)) : '?';

// Load specialties
$specs_res   = mysqli_query($conn, "SELECT * FROM specialties ORDER BY name");
$specialties = [];
while ($s = mysqli_fetch_assoc($specs_res)) $specialties[] = $s;

// Load cities
$cities_res = mysqli_query($conn, "SELECT DISTINCT city FROM doctors WHERE city IS NOT NULL AND city != '' ORDER BY city");
$cities = [];
while ($c = mysqli_fetch_assoc($cities_res)) $cities[] = $c['city'];

// Build search query
$where = "1=1";
$search     = isset($_GET['search'])    ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$spec_filter= isset($_GET['specialty']) ? mysqli_real_escape_string($conn, $_GET['specialty'])   : '';
$city_filter= isset($_GET['city'])      ? mysqli_real_escape_string($conn, $_GET['city'])        : '';

if ($search)      $where .= " AND (d.first_name LIKE '%$search%' OR d.last_name LIKE '%$search%' OR s.name LIKE '%$search%')";
if ($spec_filter) $where .= " AND s.name = '$spec_filter'";
if ($city_filter) $where .= " AND d.city = '$city_filter'";

$doctors_res = mysqli_query($conn,
    "SELECT d.id, d.first_name, d.last_name, d.city, d.phone, s.name AS specialty
     FROM doctors d
     JOIN specialties s ON d.specialty_id = s.id
     WHERE $where
     ORDER BY d.first_name");
$doctors = [];
while ($row = mysqli_fetch_assoc($doctors_res)) $doctors[] = $row;

$msg  = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$type = isset($_GET['type'])    ? $_GET['type'] : 'info';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Find Doctors – HealthCare Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet"/>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        :root{--bg:#f4f6f9;--white:#fff;--blue:#1a56db;--blue-light:#eff4ff;--green:#10b981;--border:#e5e7eb;--text-primary:#1a1f36;--text-secondary:#6b7280;--shadow:0 1px 3px rgba(0,0,0,.07);--radius:12px;--nav-h:60px;--sidebar-w:240px;}
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
        .page-header{margin-bottom:20px;}
        .page-header h2{font-size:24px;font-weight:600;margin-bottom:4px;}
        .page-header p{font-size:14px;color:var(--text-secondary);}
        .search-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px 24px;margin-bottom:20px;box-shadow:var(--shadow);}
        .search-row{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;}
        .search-group{flex:2;min-width:200px;}
        .filter-group{flex:1;min-width:150px;}
        .search-group label,.filter-group label{display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:var(--text-primary);}
        .search-input-wrap{position:relative;}
        .search-input-wrap svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:var(--text-secondary);}
        .search-input-wrap input{width:100%;padding:10px 12px 10px 38px;border:1px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;outline:none;transition:border .15s;}
        .search-input-wrap input:focus{border-color:var(--blue);}
        select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;outline:none;background:var(--white);appearance:none;cursor:pointer;}
        select:focus{border-color:var(--blue);}
        .btn-search{padding:10px 22px;background:var(--blue);color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;white-space:nowrap;}
        .btn-search:hover{background:#1446c0;}
        .results-count{font-size:14px;color:var(--text-secondary);margin-bottom:16px;}
        .doctors-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;}
        @media(max-width:1100px){.doctors-grid{grid-template-columns:repeat(2,1fr);}}
        @media(max-width:700px){.doctors-grid{grid-template-columns:1fr;}}
        .doctor-card{background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow);display:flex;flex-direction:column;gap:14px;transition:box-shadow .2s,transform .15s;}
        .doctor-card:hover{box-shadow:0 6px 20px rgba(0,0,0,.1);transform:translateY(-2px);}
        .doc-top{display:flex;align-items:flex-start;gap:14px;}
        .doc-avatar{width:56px;height:56px;border-radius:50%;background:var(--blue);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:white;flex-shrink:0;}
        .doc-name{font-size:16px;font-weight:600;margin-bottom:6px;}
        .specialty-tag{display:inline-block;background:var(--green);color:white;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;margin-bottom:6px;}
        .doc-city{font-size:13px;color:var(--text-secondary);display:flex;align-items:center;gap:5px;}
        .doc-city svg{width:13px;height:13px;}
        hr{border:none;border-top:1px solid var(--border);}
        .btn-book{width:100%;background:var(--blue);color:white;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;transition:background .15s;}
        .btn-book:hover{background:#1446c0;}
        .empty-state{grid-column:1/-1;padding:60px 24px;text-align:center;color:var(--text-secondary);}
        .flash{padding:12px 20px;border-radius:8px;margin-bottom:20px;font-size:14px;font-weight:500;}
        .flash.success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
        .flash.error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
        /* Modal */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center;}
        .modal-overlay.open{display:flex;}
        .modal{background:var(--white);border-radius:16px;padding:32px;width:480px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.15);max-height:90vh;overflow-y:auto;}
        .modal h3{font-size:20px;font-weight:600;margin-bottom:6px;}
        .modal .doc-info-sub{font-size:13px;color:var(--text-secondary);margin-bottom:20px;}
        .form-group{margin-bottom:16px;}
        .form-group label{display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:var(--text-primary);}
        .form-group input,.form-group select{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:8px;font-size:14px;font-family:inherit;outline:none;transition:border .15s;}
        .form-group input:focus,.form-group select:focus{border-color:var(--blue);}
        .slots-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:6px;}
        .slot-btn{padding:9px 6px;border:1px solid var(--border);border-radius:8px;background:var(--white);font-size:13px;font-family:inherit;cursor:pointer;text-align:center;transition:background .15s,border-color .15s;}
        .slot-btn:hover{border-color:var(--blue);background:var(--blue-light);}
        .slot-btn.selected{background:var(--blue);color:white;border-color:var(--blue);}
        .slot-btn.taken{background:#f3f4f6;color:#9ca3af;cursor:not-allowed;text-decoration:line-through;}
        .modal-actions{display:flex;gap:12px;margin-top:20px;}
        .btn-confirm{flex:1;padding:12px;background:var(--blue);color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;}
        .btn-confirm:hover{background:#1446c0;}
        .btn-cancel-modal{flex:1;padding:12px;background:var(--white);color:var(--text-primary);border:1px solid var(--border);border-radius:8px;font-size:14px;font-weight:600;font-family:inherit;cursor:pointer;}
        .btn-cancel-modal:hover{background:var(--bg);}
        #slots-loading{font-size:13px;color:var(--text-secondary);padding:8px 0;}
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
        <div class="user-meta"><div class="name"><?= $displayName ?></div><div class="role">Patient</div></div>
    </div>
</header>

<div class="layout">
    <aside>
        <nav><ul>
            <li><a href="dashboard_patient.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>Dashboard
            </a></li>
            <li><a href="find_doctor.php" class="active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>Find Doctors
            </a></li>
            <li><a href="settings.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Settings
            </a></li>
            <li><a href="../Process/logout.php" style="margin-top:8px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout
            </a></li>
        </ul></nav>
    </aside>

    <main>
        <?php if ($msg): ?><div class="flash <?= htmlspecialchars($type) ?>"><?= $msg ?></div><?php endif; ?>

        <div class="page-header">
            <h2>Find a Doctor</h2>
            <p>Search for healthcare specialists and book your appointment</p>
        </div>

        <div class="search-card">
            <form method="GET" action="">
                <div class="search-row">
                    <div class="search-group">
                        <label>Search</label>
                        <div class="search-input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" name="search" placeholder="Doctor name or specialty..." value="<?= htmlspecialchars($search) ?>"/>
                        </div>
                    </div>
                    <div class="filter-group">
                        <label>Specialty</label>
                        <select name="specialty">
                            <option value="">All Specialties</option>
                            <?php foreach ($specialties as $s): ?>
                            <option value="<?= htmlspecialchars($s['name']) ?>" <?= $spec_filter === $s['name'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>City</label>
                        <select name="city">
                            <option value="">All Cities</option>
                            <?php foreach ($cities as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= $city_filter === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-search">Search</button>
                </div>
            </form>
        </div>

        <p class="results-count"><?= count($doctors) ?> doctor<?= count($doctors) !== 1 ? 's' : '' ?> found</p>

        <div class="doctors-grid">
            <?php if (empty($doctors)): ?>
            <div class="empty-state">No doctors found matching your criteria. Try adjusting the filters.</div>
            <?php else: foreach ($doctors as $d):
                $av = strtoupper(substr($d['first_name'],0,1) . substr($d['last_name'],0,1));
            ?>
            <div class="doctor-card">
                <div class="doc-top">
                    <div class="doc-avatar"><?= $av ?></div>
                    <div>
                        <div class="doc-name">Dr. <?= htmlspecialchars($d['first_name'] . ' ' . $d['last_name']) ?></div>
                        <span class="specialty-tag"><?= htmlspecialchars($d['specialty']) ?></span>
                        <?php if ($d['city']): ?>
                        <div class="doc-city">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?= htmlspecialchars($d['city']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <hr/>
                <button class="btn-book" onclick="openBooking(<?= $d['id'] ?>, 'Dr. <?= addslashes($d['first_name'] . ' ' . $d['last_name']) ?>', '<?= addslashes($d['specialty']) ?>')">
                    Book Appointment
                </button>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </main>
</div>

<!-- Booking Modal -->
<div class="modal-overlay" id="bookingModal">
    <div class="modal">
        <h3>Book Appointment</h3>
        <p class="doc-info-sub" id="modalDocName">—</p>

        <form method="POST" action="../Process/book_appointment.php" id="bookingForm">
            <input type="hidden" name="doctor_id" id="modal_doctor_id"/>
            <input type="hidden" name="start_time" id="modal_start_time"/>
            <input type="hidden" name="end_time"   id="modal_end_time"/>

            <div class="form-group">
                <label for="appt_date">Select Date</label>
                <input type="date" id="appt_date" name="date" min="<?= date('Y-m-d') ?>" required onchange="loadSlots()"/>
            </div>

            <div class="form-group">
                <label>Available Time Slots</label>
                <div id="slots-loading" style="display:none">Loading slots…</div>
                <div class="slots-grid" id="slots-container"></div>
                <p id="no-slots" style="font-size:13px;color:var(--text-secondary);display:none">No available slots for this date.</p>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel-modal" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-confirm" id="confirmBtn" disabled>Confirm Booking</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentDoctorId = null;

function openBooking(docId, docName, specialty) {
    currentDoctorId = docId;
    document.getElementById('modal_doctor_id').value = docId;
    document.getElementById('modalDocName').textContent = docName + ' — ' + specialty;
    document.getElementById('appt_date').value = '';
    document.getElementById('slots-container').innerHTML = '';
    document.getElementById('no-slots').style.display = 'none';
    document.getElementById('confirmBtn').disabled = true;
    document.getElementById('bookingModal').classList.add('open');
}

function closeModal() {
    document.getElementById('bookingModal').classList.remove('open');
}

document.getElementById('bookingModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

function loadSlots() {
    const date = document.getElementById('appt_date').value;
    if (!date || !currentDoctorId) return;

    document.getElementById('slots-loading').style.display = 'block';
    document.getElementById('slots-container').innerHTML = '';
    document.getElementById('no-slots').style.display = 'none';
    document.getElementById('confirmBtn').disabled = true;
    document.getElementById('modal_start_time').value = '';
    document.getElementById('modal_end_time').value = '';

    fetch('get_slots.php?doctor_id=' + currentDoctorId + '&date=' + date)
        .then(r => r.json())
        .then(data => {
            document.getElementById('slots-loading').style.display = 'none';
            const container = document.getElementById('slots-container');
            if (!data.slots || data.slots.length === 0) {
                document.getElementById('no-slots').style.display = 'block';
                return;
            }
            data.slots.forEach(slot => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'slot-btn' + (slot.taken ? ' taken' : '');
                btn.textContent = slot.start;
                btn.disabled = slot.taken;
                if (!slot.taken) {
                    btn.onclick = function() {
                        document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
                        btn.classList.add('selected');
                        document.getElementById('modal_start_time').value = slot.start;
                        document.getElementById('modal_end_time').value   = slot.end;
                        document.getElementById('confirmBtn').disabled = false;
                    };
                }
                container.appendChild(btn);
            });
        })
        .catch(() => {
            document.getElementById('slots-loading').style.display = 'none';
            document.getElementById('no-slots').style.display = 'block';
        });
}
</script>
</body>
</html>
