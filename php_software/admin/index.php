<?php
/**
 * Balal Mobile & EasyPaisa POS - Staff Accounts & Role Permissions Manager
 * Dedicated local store management: Add/edit staff, assign terminal PINs, and configure granular permissions.
 */
$pageTitle = 'اسٹاف مینیجر و اختیارات (Staff & Permissions)';
$activeMenu = 'admin';

require_once __DIR__ . '/../backend/header.php';

// Strict Role Guard: Only Owner, Admin or Manager can access Staff Manager
$currentUser = getLoggedInUser();
$userRole = $currentUser['role'] ?? 'Staff';
if ($userRole !== 'Owner' && $userRole !== 'Admin' && $userRole !== 'SuperAdmin') {
    echo "<script>window.location.href = '../dashboard/index.php?access_denied=1';</script>";
    exit();
}

$alertMsg = '';
$alertType = 'success';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Create New Staff Account
    if ($action === 'create_user') {
        $name = trim($_POST['name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? 'Cashier');
        $pin = trim($_POST['pin'] ?? '1234');
        $password = trim($_POST['password'] ?? '123456');
        $status = $_POST['status'] ?? 'ACTIVE';
        
        $selectedPerms = $_POST['permissions'] ?? [];
        if ($role === 'Owner' || $role === 'Admin') {
            $selectedPerms = ['all', 'pos', 'inventory', 'purchases', 'khata', 'easypaisa', 'reports', 'settings', 'admin'];
        }
        $permsJson = json_encode($selectedPerms, JSON_UNESCAPED_UNICODE);

        if (empty($name) || empty($email) || empty($password)) {
            $alertMsg = $isUrdu ? 'برائے مہربانی نام، ای میل اور پاسورڈ درج کریں۔' : 'Please provide name, email and password.';
            $alertType = 'error';
        } else {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(email) = LOWER(:email)");
                $stmt->execute([':email' => $email]);
                if (intval($stmt->fetchColumn()) > 0) {
                    $alertMsg = $isUrdu ? 'یہ ای میل پہلے سے سسٹم میں موجود ہے۔' : 'This email address is already registered.';
                    $alertType = 'error';
                } else {
                    $newId = 'usr-' . strtolower(substr($role, 0, 3)) . '-' . rand(100, 999);
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $now = time();

                    $stmt = $pdo->prepare("INSERT INTO users (id, name, email, password_hash, pin_code, role, status, permissions, shop_name, phone, last_login, created_at) VALUES (:id, :name, :email, :hash, :pin, :role, :status, :perms, :shop, :phone, 0, :created_at)");
                    $stmt->execute([
                        ':id' => $newId,
                        ':name' => $name,
                        ':email' => $email,
                        ':hash' => $hash,
                        ':pin' => !empty($pin) ? $pin : '1234',
                        ':role' => $role,
                        ':status' => $status,
                        ':perms' => $permsJson,
                        ':shop' => $shopName,
                        ':phone' => $phone,
                        ':created_at' => $now
                    ]);

                    logUserActivity($pdo, 'USER_CREATE', "Created new {$role} account: {$name} ({$email})");
                    $alertMsg = $isUrdu ? "نیا اسٹاف ممبر '{$name}' بطور '{$role}' کامیابی سے شامل کر دیا گیا۔" : "Staff member '{$name}' added successfully as '{$role}'.";
                    $alertType = 'success';
                }
            } catch (Exception $e) {
                $alertMsg = "Error: " . $e->getMessage();
                $alertType = 'error';
            }
        }
    }

    // 2. Edit Staff Details & Permissions
    if ($action === 'edit_user') {
        $userId = trim($_POST['user_id'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = trim($_POST['role'] ?? 'Cashier');
        $pin = trim($_POST['pin'] ?? '');
        $status = $_POST['status'] ?? 'ACTIVE';

        $selectedPerms = $_POST['permissions'] ?? [];
        if ($role === 'Owner' || $role === 'Admin') {
            $selectedPerms = ['all', 'pos', 'inventory', 'purchases', 'khata', 'easypaisa', 'reports', 'settings', 'admin'];
        }
        $permsJson = json_encode($selectedPerms, JSON_UNESCAPED_UNICODE);

        if (!empty($userId) && !empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = :name, phone = :phone, role = :role, pin_code = :pin, status = :status, permissions = :perms WHERE id = :id");
                $stmt->execute([
                    ':name' => $name,
                    ':phone' => $phone,
                    ':role' => $role,
                    ':pin' => $pin,
                    ':status' => $status,
                    ':perms' => $permsJson,
                    ':id' => $userId
                ]);

                logUserActivity($pdo, 'USER_UPDATE', "Updated account details & permissions for user ID: {$userId} ({$name})");
                $alertMsg = $isUrdu ? "اسٹاف ممبر '{$name}' کا ریکارڈ و اختیارات کامیابی سے اپ ڈیٹ ہو گئے۔" : "Staff '{$name}' updated successfully.";
                $alertType = 'success';
            } catch (Exception $e) {
                $alertMsg = "Error: " . $e->getMessage();
                $alertType = 'error';
            }
        }
    }

    // 3. Reset Staff Password
    if ($action === 'reset_password') {
        $userId = trim($_POST['user_id'] ?? '');
        $newPass = trim($_POST['new_password'] ?? '');
        $userName = trim($_POST['user_name'] ?? 'User');

        if (!empty($userId) && !empty($newPass)) {
            try {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                $stmt->execute([':hash' => $hash, ':id' => $userId]);

                logUserActivity($pdo, 'PASSWORD_RESET', "Reset password for staff: {$userName} ({$userId})");
                $alertMsg = $isUrdu ? "اسٹاف ممبر '{$userName}' کا نیا پاسورڈ محفوظ ہو گیا۔" : "Password for '{$userName}' has been reset successfully.";
                $alertType = 'success';
            } catch (Exception $e) {
                $alertMsg = "Error: " . $e->getMessage();
                $alertType = 'error';
            }
        }
    }

    // 4. Toggle Staff Status (Active / Suspended)
    if ($action === 'toggle_status') {
        $userId = trim($_POST['user_id'] ?? '');
        $newStatus = trim($_POST['new_status'] ?? 'ACTIVE');
        $userName = trim($_POST['user_name'] ?? 'User');

        if ($userId === ($currentUser['id'] ?? '')) {
            $alertMsg = $isUrdu ? 'آپ اپنے ذاتی اکاؤنٹ کی حالت تبدیل نہیں کر سکتے۔' : 'You cannot change your own account status.';
            $alertType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
                $stmt->execute([':status' => $newStatus, ':id' => $userId]);

                logUserActivity($pdo, 'STATUS_CHANGE', "Changed status of {$userName} to {$newStatus}");
                $alertMsg = $isUrdu ? "صارف '{$userName}' کا اسٹیٹس اب '{$newStatus}' ہے۔" : "Status for '{$userName}' changed to {$newStatus}.";
                $alertType = 'success';
            } catch (Exception $e) {
                $alertMsg = "Error: " . $e->getMessage();
                $alertType = 'error';
            }
        }
    }

    // 5. Delete Staff Account
    if ($action === 'delete_user') {
        $userId = trim($_POST['user_id'] ?? '');
        $userName = trim($_POST['user_name'] ?? 'User');

        if ($userId === ($currentUser['id'] ?? '')) {
            $alertMsg = $isUrdu ? 'آپ اپنا ذاتی ایڈمن اکاؤنٹ ڈیلیٹ نہیں کر سکتے۔' : 'You cannot delete your own logged-in account.';
            $alertType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $userId]);

                logUserActivity($pdo, 'USER_DELETE', "Deleted staff account: {$userName} (ID: {$userId})");
                $alertMsg = $isUrdu ? "اسٹاف ممبر '{$userName}' کا اکاؤنٹ کامیابی سے ڈیلیٹ کر دیا گیا۔" : "Staff account '{$userName}' deleted successfully.";
                $alertType = 'success';
            } catch (Exception $e) {
                $alertMsg = "Error: " . $e->getMessage();
                $alertType = 'error';
            }
        }
    }
}

// Fetch Staff Users
$users = [];
try {
    $stmt = $pdo->query("SELECT * FROM users ORDER BY (CASE WHEN role = 'Owner' THEN 1 WHEN role = 'Admin' THEN 2 WHEN role = 'Manager' THEN 3 ELSE 4 END), created_at DESC");
    $users = $stmt->fetchAll();
} catch (Exception $e) {}

// Filter out SuperAdmin/CEO from shop staff view so regular shop view is completely clean
$shopStaffUsers = array_filter($users, fn($u) => ($u['role'] ?? '') !== 'SuperAdmin' && strtolower($u['email'] ?? '') !== 'admin@limopos.com');

// Fetch Activity Logs
$activityLogs = [];
try {
    $stmt = $pdo->query("SELECT * FROM user_activity_logs ORDER BY created_at DESC LIMIT 30");
    $activityLogs = $stmt->fetchAll();
} catch (Exception $e) {}

// Metrics Calculation
$totalStaff = count($shopStaffUsers);
$activeStaff = count(array_filter($shopStaffUsers, fn($u) => ($u['status'] ?? 'ACTIVE') === 'ACTIVE'));
$cashiersCount = count(array_filter($shopStaffUsers, fn($u) => ($u['role'] ?? '') === 'Cashier'));
$managersCount = count(array_filter($shopStaffUsers, fn($u) => ($u['role'] ?? '') === 'Manager'));

$isCeo = isCeoAdmin();
$activeTab = $_GET['tab'] ?? 'staff';
?>

<div class="space-y-6">

    <!-- Top Alert Notification -->
    <?php if (!empty($alertMsg)): ?>
        <div class="p-4 rounded-2xl border text-sm font-bold flex items-center justify-between shadow-lg transition-all <?= $alertType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-600 dark:text-rose-400' ?>">
            <div class="flex items-center gap-2.5">
                <i data-lucide="<?= $alertType === 'success' ? 'check-circle-2' : 'alert-octagon' ?>" class="w-5 h-5 shrink-0"></i>
                <span><?= htmlspecialchars($alertMsg) ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="p-1 rounded-lg hover:bg-black/10 transition-colors">✕</button>
        </div>
    <?php endif; ?>

    <!-- Staff Header Banner -->
    <div class="p-6 rounded-3xl bg-gradient-to-r from-slate-900 via-slate-850 to-slate-900 border border-slate-700/80 text-white shadow-xl relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-limoblue-600 to-emerald-500 text-white flex items-center justify-center font-black shadow-lg shadow-limoblue-500/20 shrink-0">
                    <i data-lucide="users" class="w-8 h-8"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h2 class="text-xl sm:text-2xl font-black text-white">
                            <?= $isUrdu ? 'اسٹاف مینیجر و اختیارات کنٹرول' : 'Staff Accounts & Role Permissions' ?>
                        </h2>
                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-xs font-mono font-bold">
                            🛡️ <?= $isUrdu ? 'دکان اسٹاف کنٹرول' : 'Staff Control' ?>
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-300 mt-1">
                        <?= $isUrdu ? 'اپنی دکان کے عملہ، منیجرز، اور کیشیئرز کے اکاؤنٹس بنائیں اور ان کو مخصوص سافٹ ویئر ماڈیولز کے اختیارات تفویض کریں۔' : 'Create cashier/manager accounts, set POS PINs, and grant granular feature permissions.' ?>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2.5 self-end md:self-auto flex-wrap">
                <?php if ($isCeo): ?>
                    <a href="../ceo/index.php" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 text-slate-950 font-black text-xs sm:text-sm shadow-lg shadow-amber-500/30 flex items-center gap-2 cursor-pointer transition-all hover:scale-105">
                        <i data-lucide="crown" class="w-4 h-4"></i>
                        <span><?= $isUrdu ? 'سی ای او ماسٹر پورٹل (HQ)' : 'CEO Master Portal' ?></span>
                    </a>
                <?php endif; ?>
                
                <button type="button" onclick="openAddUserModal()" class="px-4 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-extrabold text-xs sm:text-sm shadow-lg shadow-emerald-600/30 flex items-center gap-2 cursor-pointer transition-all">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span><?= $isUrdu ? 'نیا اسٹاف ممبر شامل کریں' : 'Add Staff Member' ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Overview Stat Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center shrink-0">
                <i data-lucide="users" class="w-6 h-6"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-bold block"><?= $isUrdu ? 'کل اسٹاف ممبرز' : 'Total Staff' ?></span>
                <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white"><?= $totalStaff ?></span>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center shrink-0">
                <i data-lucide="user-check" class="w-6 h-6"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-bold block"><?= $isUrdu ? 'فعال اسٹاف (Active)' : 'Active Staff' ?></span>
                <span class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400"><?= $activeStaff ?></span>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center shrink-0">
                <i data-lucide="shopping-cart" class="w-6 h-6"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-bold block"><?= $isUrdu ? 'کیشیئرز (Cashiers)' : 'Cashiers' ?></span>
                <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white"><?= $cashiersCount ?></span>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-purple-500/10 text-purple-500 flex items-center justify-center shrink-0">
                <i data-lucide="briefcase" class="w-6 h-6"></i>
            </div>
            <div>
                <span class="text-xs text-slate-400 font-bold block"><?= $isUrdu ? 'اسٹور منیجرز' : 'Managers' ?></span>
                <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white"><?= $managersCount ?></span>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200 dark:border-slate-800 text-xs sm:text-sm font-extrabold scrollbar-none">
        <button type="button" onclick="switchStaffTab('staff')" id="tabBtnStaff" class="px-4 py-2.5 rounded-xl bg-emerald-600 text-white shadow-md transition-all flex items-center gap-2 shrink-0 cursor-pointer">
            <i data-lucide="users" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'اسٹاف ممبرز لسٹ (Staff List)' : 'Staff List' ?></span>
        </button>

        <button type="button" onclick="switchStaffTab('permissions')" id="tabBtnPerms" class="px-4 py-2.5 rounded-xl text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all flex items-center gap-2 shrink-0 cursor-pointer">
            <i data-lucide="shield" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'اختیارات گائیڈ (Permissions Matrix)' : 'Permissions Matrix' ?></span>
        </button>

        <button type="button" onclick="switchStaffTab('logs')" id="tabBtnLogs" class="px-4 py-2.5 rounded-xl text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all flex items-center gap-2 shrink-0 cursor-pointer">
            <i data-lucide="history" class="w-4 h-4"></i>
            <span><?= $isUrdu ? 'اسٹاف ایکٹیویٹی لاگ (Activity Logs)' : 'Staff Activity Logs' ?></span>
        </button>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 1: STAFF LIST & PERMISSIONS MANAGEMENT -->
    <!-- ========================================================================= -->
    <div id="tabContentStaff" class="space-y-4">
        
        <!-- Search and Filter Bar -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 shadow-sm">
            <div class="relative w-full sm:w-72">
                <i data-lucide="search" class="w-4 h-4 absolute inset-y-0 my-auto <?= $isUrdu ? 'right-3' : 'left-3' ?> text-slate-400"></i>
                <input
                    type="text"
                    id="userSearchInput"
                    onkeyup="filterStaffTable()"
                    placeholder="<?= $isUrdu ? 'نام، ای میل، فون یا رول سے تلاش کریں...' : 'Search by name, email, role...' ?>"
                    class="w-full <?= $isUrdu ? 'pr-9 pl-3' : 'pl-9 pr-3' ?> py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500"
                />
            </div>

            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <span class="text-xs text-slate-400 font-bold"><?= $isUrdu ? 'رول فلٹر:' : 'Role Filter:' ?></span>
                <select id="roleFilterSelect" onchange="filterStaffTable()" class="px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none">
                    <option value="ALL"><?= $isUrdu ? 'تمام رولز (All Roles)' : 'All Roles' ?></option>
                    <option value="Owner"><?= $isUrdu ? 'مالک (Owner)' : 'Owner' ?></option>
                    <option value="Manager"><?= $isUrdu ? 'منیجر (Manager)' : 'Manager' ?></option>
                    <option value="Cashier"><?= $isUrdu ? 'کیشیئر (Cashier)' : 'Cashier' ?></option>
                    <option value="Salesperson"><?= $isUrdu ? 'سیلز پرسن (Salesperson)' : 'Salesperson' ?></option>
                </select>
            </div>
        </div>

        <!-- Users Table -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-slate-700 dark:text-slate-300" id="usersTable">
                    <thead class="bg-slate-50 dark:bg-slate-800/80 text-[11px] font-black uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-3.5 <?= $isUrdu ? 'text-right' : 'text-left' ?>"><?= $isUrdu ? 'اسٹاف ممبر / نام' : 'Staff Member / Name' ?></th>
                            <th class="p-3.5 <?= $isUrdu ? 'text-right' : 'text-left' ?>"><?= $isUrdu ? 'ای میل و فون' : 'Email & Phone' ?></th>
                            <th class="p-3.5 text-center"><?= $isUrdu ? 'عہدہ / رول' : 'Role' ?></th>
                            <th class="p-3.5 text-center"><?= $isUrdu ? 'لاگ ان پن (PIN)' : 'POS PIN' ?></th>
                            <th class="p-3.5 text-center"><?= $isUrdu ? 'اختیارات (Permissions)' : 'Permissions' ?></th>
                            <th class="p-3.5 text-center"><?= $isUrdu ? 'اکاؤنٹ اسٹیٹس' : 'Status' ?></th>
                            <th class="p-3.5 text-center"><?= $isUrdu ? 'ایکشنز' : 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        <?php foreach ($shopStaffUsers as $user): 
                            $roleName = $user['role'] ?? 'Staff';
                            $status = $user['status'] ?? 'ACTIVE';
                            $isSuspended = strtoupper($status) === 'SUSPENDED';
                            $isSelf = ($user['id'] === ($currentUser['id'] ?? ''));

                            // Permissions array
                            $perms = [];
                            if (!empty($user['permissions'])) {
                                $decoded = json_decode($user['permissions'], true);
                                $perms = is_array($decoded) ? $decoded : [];
                            }

                            // Role Badge Styling
                            $roleBg = match($roleName) {
                                'Owner' => 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30',
                                'Manager' => 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border-blue-500/30',
                                'Cashier' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
                                default => 'bg-slate-500/15 text-slate-600 dark:text-slate-400 border-slate-500/30',
                            };
                            $roleIcon = match($roleName) {
                                'Owner' => '👑',
                                'Manager' => '💼',
                                'Cashier' => '⚡',
                                default => '🏷️',
                            };
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors user-row" data-role="<?= htmlspecialchars($roleName) ?>" data-search="<?= strtolower(htmlspecialchars($user['name'] . ' ' . $user['email'] . ' ' . ($user['phone'] ?? '') . ' ' . $roleName)) ?>">
                            <!-- Staff Name & Avatar -->
                            <td class="p-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl <?= $roleBg ?> font-black text-xs flex items-center justify-center shrink-0 border">
                                        <?= mb_substr($user['name'], 0, 1, 'UTF-8') ?>
                                    </div>
                                    <div>
                                        <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                                            <span><?= htmlspecialchars($user['name']) ?></span>
                                            <?php if ($isSelf): ?>
                                                <span class="px-1.5 py-0.2 rounded bg-emerald-500/20 text-emerald-500 text-[10px] font-bold"><?= $isUrdu ? 'آپ خود' : 'You' ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <span class="text-[10px] text-slate-400 font-mono">ID: <?= htmlspecialchars($user['id']) ?></span>
                                    </div>
                                </div>
                            </td>

                            <!-- Email & Phone -->
                            <td class="p-3.5">
                                <div class="font-mono text-slate-800 dark:text-slate-200"><?= htmlspecialchars($user['email']) ?></div>
                                <div class="text-[11px] text-slate-400"><?= htmlspecialchars($user['phone'] ?? '—') ?></div>
                            </td>

                            <!-- Role -->
                            <td class="p-3.5 text-center">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold border <?= $roleBg ?>">
                                    <span><?= $roleIcon ?></span>
                                    <span><?= htmlspecialchars($roleName) ?></span>
                                </span>
                            </td>

                            <!-- PIN Code -->
                            <td class="p-3.5 text-center">
                                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 font-mono font-black text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                    <?= htmlspecialchars($user['pin_code'] ?? '----') ?>
                                </span>
                            </td>

                            <!-- Granular Permissions Badges -->
                            <td class="p-3.5 text-center">
                                <?php if ($roleName === 'Owner' || in_array('all', $perms)): ?>
                                    <span class="px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-500 text-[10px] font-black border border-amber-500/30">
                                        🌟 <?= $isUrdu ? 'مکمل اختیارات (Full Access)' : 'Full Access' ?>
                                    </span>
                                <?php else: ?>
                                    <div class="flex items-center justify-center gap-1 flex-wrap max-w-xs mx-auto">
                                        <?php if (in_array('pos', $perms)): ?>
                                            <span class="px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-500 text-[9px] font-bold">POS</span>
                                        <?php endif; ?>
                                        <?php if (in_array('inventory', $perms)): ?>
                                            <span class="px-1.5 py-0.5 rounded bg-blue-500/10 text-blue-500 text-[9px] font-bold">Stock</span>
                                        <?php endif; ?>
                                        <?php if (in_array('purchases', $perms)): ?>
                                            <span class="px-1.5 py-0.5 rounded bg-teal-500/10 text-teal-500 text-[9px] font-bold">Purchases</span>
                                        <?php endif; ?>
                                        <?php if (in_array('khata', $perms)): ?>
                                            <span class="px-1.5 py-0.5 rounded bg-purple-500/10 text-purple-500 text-[9px] font-bold">Khata</span>
                                        <?php endif; ?>
                                        <?php if (in_array('easypaisa', $perms)): ?>
                                            <span class="px-1.5 py-0.5 rounded bg-limogreen-500/10 text-limogreen-500 text-[9px] font-bold">EasyPaisa</span>
                                        <?php endif; ?>
                                        <?php if (in_array('reports', $perms)): ?>
                                            <span class="px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-500 text-[9px] font-bold">Reports</span>
                                        <?php endif; ?>
                                        <?php if (empty($perms)): ?>
                                            <span class="text-slate-400 text-[10px]">No Modules</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td class="p-3.5 text-center">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1 <?= $isSuspended ? 'bg-rose-500/15 text-rose-500 border border-rose-500/30' : 'bg-emerald-500/15 text-emerald-500 border border-emerald-500/30' ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $isSuspended ? 'bg-rose-500' : 'bg-emerald-500' ?>"></span>
                                    <span><?= $isSuspended ? ($isUrdu ? 'معطل (Suspended)' : 'Suspended') : ($isUrdu ? 'فعال (Active)' : 'Active') ?></span>
                                </span>
                            </td>

                            <!-- Action Buttons -->
                            <td class="p-3.5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <!-- Edit Staff & Permissions -->
                                    <button 
                                        type="button" 
                                        onclick="openEditUserModal(<?= htmlspecialchars(json_encode($user)) ?>)"
                                        class="p-1.5 rounded-lg bg-blue-500/10 text-blue-500 hover:bg-blue-500 hover:text-white transition-colors"
                                        title="<?= $isUrdu ? 'اسٹاف اور اختیارات ایڈٹ کریں' : 'Edit Staff & Permissions' ?>"
                                    >
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </button>

                                    <!-- Reset Password -->
                                    <button 
                                        type="button" 
                                        onclick="openResetPassModal('<?= $user['id'] ?>', '<?= htmlspecialchars($user['name']) ?>')"
                                        class="p-1.5 rounded-lg bg-amber-500/10 text-amber-500 hover:bg-amber-500 hover:text-white transition-colors"
                                        title="<?= $isUrdu ? 'پاسورڈ تبدیل کریں' : 'Reset Password' ?>"
                                    >
                                        <i data-lucide="key" class="w-4 h-4"></i>
                                    </button>

                                    <?php if (!$isSelf && $roleName !== 'Owner'): ?>
                                        <!-- Suspend / Activate Toggle -->
                                        <form method="POST" class="inline" onsubmit="return confirm('<?= $isSuspended ? ($isUrdu ? 'کیا آپ اس صارف کو دوبارہ فعال کرنا چاہتے ہیں؟' : 'Activate this staff member?') : ($isUrdu ? 'کیا آپ اس صارف کا اکاؤنٹ معطل (Suspend) کرنا چاہتے ہیں؟' : 'Suspend this staff member?') ?>')">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="user_name" value="<?= htmlspecialchars($user['name']) ?>">
                                            <input type="hidden" name="new_status" value="<?= $isSuspended ? 'ACTIVE' : 'SUSPENDED' ?>">
                                            <button 
                                                type="submit" 
                                                class="p-1.5 rounded-lg <?= $isSuspended ? 'bg-emerald-500/10 text-emerald-500 hover:bg-emerald-500' : 'bg-slate-500/10 text-slate-500 hover:bg-slate-500' ?> hover:text-white transition-colors"
                                                title="<?= $isSuspended ? ($isUrdu ? 'اکاؤنٹ بحال کریں' : 'Reactivate') : ($isUrdu ? 'معطل کریں' : 'Suspend') ?>"
                                            >
                                                <i data-lucide="<?= $isSuspended ? 'user-check' : 'user-x' ?>" class="w-4 h-4"></i>
                                            </button>
                                        </form>

                                        <!-- Delete User -->
                                        <form method="POST" class="inline" onsubmit="return confirm('<?= $isUrdu ? 'کیا آپ واقعی اس اسٹاف اکاؤنٹ کو ڈیلیٹ کرنا چاہتے ہیں؟' : 'Are you sure you want to delete this staff member?' ?>')">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="user_name" value="<?= htmlspecialchars($user['name']) ?>">
                                            <button 
                                                type="submit" 
                                                class="p-1.5 rounded-lg bg-rose-500/10 text-rose-500 hover:bg-rose-500 hover:text-white transition-colors"
                                                title="<?= $isUrdu ? 'ڈیلیٹ کریں' : 'Delete Staff' ?>"
                                            >
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 2: ROLES & PERMISSIONS MATRIX GUIDE -->
    <!-- ========================================================================= -->
    <div id="tabContentPerms" class="space-y-4 hidden">
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white">
                    <?= $isUrdu ? 'دکان کے عہدے اور اختیارات کی وضاحت' : 'Store Roles & Permissions Matrix' ?>
                </h3>
                <p class="text-xs text-slate-500 mt-1">
                    <?= $isUrdu ? 'ہر رول کے پاس مختلف ماڈیولز تک رسائی کی سطح درج ذیل ہے:' : 'Overview of access levels and module restrictions per role:' ?>
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                <!-- Owner / Admin -->
                <div class="p-4 rounded-2xl border-2 border-amber-500/30 bg-amber-500/5 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">👑</span>
                        <h4 class="font-black text-sm text-slate-900 dark:text-white"><?= $isUrdu ? 'دکان مالک (Owner / Admin)' : 'Store Owner / Admin' ?></h4>
                    </div>
                    <ul class="text-xs space-y-1.5 text-slate-600 dark:text-slate-300">
                        <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">✓ <?= $isUrdu ? 'تمام ماڈیولز تک مکمل لامحدود رسائی' : 'Full unrestricted access to all modules' ?></li>
                        <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">✓ <?= $isUrdu ? 'اسٹاف ممبرز بنانا اور اختیارات دینا' : 'Create & edit staff accounts' ?></li>
                        <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">✓ <?= $isUrdu ? 'منافع و نقصان رپورٹس اور اخراجات' : 'View net profit margins & sales stats' ?></li>
                        <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">✓ <?= $isUrdu ? 'دکان پروفائل اور ڈیٹا بیک اپ' : 'Shop profile & database backups' ?></li>
                    </ul>
                </div>

                <!-- Store Manager -->
                <div class="p-4 rounded-2xl border-2 border-blue-500/30 bg-blue-500/5 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">💼</span>
                        <h4 class="font-black text-sm text-slate-900 dark:text-white"><?= $isUrdu ? 'اسٹور منیجر (Store Manager)' : 'Store Manager' ?></h4>
                    </div>
                    <ul class="text-xs space-y-1.5 text-slate-600 dark:text-slate-300">
                        <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">✓ <?= $isUrdu ? 'سیلز بلنگ اور رسید پرنٹنگ' : 'POS billing & invoice prints' ?></li>
                        <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">✓ <?= $isUrdu ? 'اسٹاک انٹری، قیمتیں، اور خریداری' : 'Stock inventory & purchase register' ?></li>
                        <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">✓ <?= $isUrdu ? 'کسٹمر و سپلائر کھاتہ منیجمنٹ' : 'Customer & Supplier udhaar ledger' ?></li>
                        <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">✓ <?= $isUrdu ? 'ایزی پیسہ و کیش رجسٹر اندراج' : 'EasyPaisa & Cash drawer transactions' ?></li>
                        <li class="flex items-center gap-1.5 text-rose-500 font-bold">✕ <?= $isUrdu ? 'نئے ایڈمن بنانے کی اجازت نہیں' : 'Cannot manage store owner account' ?></li>
                    </ul>
                </div>

                <!-- POS Cashier -->
                <div class="p-4 rounded-2xl border-2 border-emerald-500/30 bg-emerald-500/5 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">⚡</span>
                        <h4 class="font-black text-sm text-slate-900 dark:text-white"><?= $isUrdu ? 'پی او ایس کیشیئر (POS Cashier)' : 'POS Cashier' ?></h4>
                    </div>
                    <ul class="text-xs space-y-1.5 text-slate-600 dark:text-slate-300">
                        <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">✓ <?= $isUrdu ? 'فوری بارکوڈ سیلز اور بل پرنٹ' : 'POS billing & thermal receipt print' ?></li>
                        <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">✓ <?= $isUrdu ? 'ایزی پیسہ کیش ان / کیش آؤٹ' : 'EasyPaisa cash-in & cash-out' ?></li>
                        <li class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-bold">✓ <?= $isUrdu ? 'بارکوڈ لیبل پرنٹنگ' : 'Barcode label prints' ?></li>
                        <li class="flex items-center gap-1.5 text-rose-500 font-bold">✕ <?= $isUrdu ? 'خریداری لاگت و خالص منافع چھپا ہوا' : 'Purchase costs & net profits hidden' ?></li>
                        <li class="flex items-center gap-1.5 text-rose-500 font-bold">✕ <?= $isUrdu ? 'سیٹنگز اور اسٹاف مینیجر تک رسائی بند' : 'No access to settings or staff manager' ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- TAB 3: STAFF ACTIVITY AUDIT LOG -->
    <!-- ========================================================================= -->
    <div id="tabContentLogs" class="space-y-4 hidden">
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="font-black text-slate-900 dark:text-white text-sm"><?= $isUrdu ? 'اسٹاف لاگ ان و سیکیورٹی آڈٹ ٹریل' : 'Staff Security & Action Audit Trail' ?></h3>
                    <p class="text-xs text-slate-400"><?= $isUrdu ? 'کون سے ملازم نے کب لاگ ان کیا یا کوئی تبدیلی کی' : 'Track recent login timestamps and data modifications' ?></p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left text-slate-700 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-800/80 text-[11px] font-black uppercase text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="p-3.5 <?= $isUrdu ? 'text-right' : 'text-left' ?>"><?= $isUrdu ? 'وقت / تاریخ' : 'Time & Date' ?></th>
                            <th class="p-3.5 <?= $isUrdu ? 'text-right' : 'text-left' ?>"><?= $isUrdu ? 'صارف / رول' : 'User / Role' ?></th>
                            <th class="p-3.5 text-center"><?= $isUrdu ? 'ایکشن' : 'Action' ?></th>
                            <th class="p-3.5 <?= $isUrdu ? 'text-right' : 'text-left' ?>"><?= $isUrdu ? 'تفصیلات' : 'Details' ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php if (empty($activityLogs)): ?>
                            <tr>
                                <td colspan="4" class="p-8 text-center text-slate-400 text-xs">
                                    <?= $isUrdu ? 'کوئی لاگ ریکارڈ موجود نہیں۔' : 'No audit log records found.' ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activityLogs as $log): ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="p-3.5 font-mono text-slate-500 whitespace-nowrap">
                                        <?= date('d M Y, h:i A', $log['created_at'] ?? time()) ?>
                                    </td>
                                    <td class="p-3.5">
                                        <div class="font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></div>
                                        <span class="text-[10px] text-slate-400"><?= htmlspecialchars($log['role'] ?? 'Staff') ?></span>
                                    </td>
                                    <td class="p-3.5 text-center">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                            <?= htmlspecialchars($log['action'] ?? 'ACTION') ?>
                                        </span>
                                    </td>
                                    <td class="p-3.5 text-slate-600 dark:text-slate-400">
                                        <?= htmlspecialchars($log['details'] ?? '—') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: ADD NEW STAFF MEMBER -->
<!-- ========================================================================= -->
<div id="addUserModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 my-8">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-500 flex items-center justify-center font-bold">
                    <i data-lucide="user-plus" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-black text-slate-900 dark:text-white text-base"><?= $isUrdu ? 'نیا اسٹاف ممبر شامل کریں' : 'Add New Staff Member' ?></h3>
                    <p class="text-xs text-slate-400"><?= $isUrdu ? 'لاگ ان اکاؤنٹ اور اختیارات تفویض کریں' : 'Create credentials & set module permissions' ?></p>
                </div>
            </div>
            <button type="button" onclick="closeAddUserModal()" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800">✕</button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="create_user">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1"><?= $isUrdu ? 'اسٹاف ممبر کا نام *' : 'Staff Full Name *' ?></label>
                    <input type="text" name="name" required placeholder="Ali Raza" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1"><?= $isUrdu ? 'موبائل فون نمبر' : 'Phone Number' ?></label>
                    <input type="text" name="phone" placeholder="0300-1234567" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1"><?= $isUrdu ? 'ای میل (لاگ ان کے لیے) *' : 'Email Address *' ?></label>
                    <input type="email" name="email" required placeholder="cashier@shop.com" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1"><?= $isUrdu ? 'عہدہ / رول *' : 'Role *' ?></label>
                    <select name="role" id="addRoleSelect" onchange="autoCheckPermissions('add')" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500">
                        <option value="Cashier"><?= $isUrdu ? 'کیشیئر (POS Cashier)' : 'POS Cashier' ?></option>
                        <option value="Manager"><?= $isUrdu ? 'اسٹور منیجر (Store Manager)' : 'Store Manager' ?></option>
                        <option value="Salesperson"><?= $isUrdu ? 'سیلز پرسن (Salesperson)' : 'Salesperson' ?></option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1"><?= $isUrdu ? 'سیکیورٹی پن (4 ہندسے) *' : 'Quick POS PIN (4 Digits) *' ?></label>
                    <input type="text" name="pin" maxlength="6" value="1234" required class="w-full px-3 py-2 text-xs font-mono font-black rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1"><?= $isUrdu ? 'پاسورڈ *' : 'Password *' ?></label>
                    <input type="password" name="password" value="123456" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-emerald-500">
                </div>
            </div>

            <!-- Granular Permissions Selector -->
            <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <label class="block text-xs font-black text-slate-800 dark:text-slate-200">
                    <?= $isUrdu ? 'سافٹ ویئر کے اختیارات (Module Permissions):' : 'Module Access Permissions:' ?>
                </label>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs" id="addPermsContainer">
                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="pos" checked class="rounded text-emerald-600 focus:ring-0">
                        <span>🛒 <?= $isUrdu ? 'سیل اینڈ POS' : 'POS Billing' ?></span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="inventory" class="rounded text-emerald-600 focus:ring-0">
                        <span>📦 <?= $isUrdu ? 'اسٹاک انوینٹری' : 'Stock' ?></span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="purchases" class="rounded text-emerald-600 focus:ring-0">
                        <span>📱 <?= $isUrdu ? 'موبائل خریداری' : 'Purchases' ?></span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="khata" class="rounded text-emerald-600 focus:ring-0">
                        <span>👥 <?= $isUrdu ? 'ادھار و کھاتہ' : 'Khata' ?></span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="easypaisa" checked class="rounded text-emerald-600 focus:ring-0">
                        <span>💵 <?= $isUrdu ? 'ایزی پیسہ' : 'EasyPaisa' ?></span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="barcode" checked class="rounded text-emerald-600 focus:ring-0">
                        <span>🏷️ <?= $isUrdu ? 'بارکوڈ پرنٹ' : 'Barcodes' ?></span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="reports" class="rounded text-emerald-600 focus:ring-0">
                        <span>📊 <?= $isUrdu ? 'رپورٹس' : 'Reports' ?></span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="photo_vault" class="rounded text-emerald-600 focus:ring-0">
                        <span>📷 <?= $isUrdu ? 'فوٹو والٹ' : 'Vault' ?></span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeAddUserModal()" class="px-4 py-2 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <?= $isUrdu ? 'منسوخ' : 'Cancel' ?>
                </button>
                <button type="submit" class="px-5 py-2 text-xs font-black rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-md hover:from-emerald-500 hover:to-teal-500">
                    <?= $isUrdu ? 'اسٹاف محفوظ کریں' : 'Save Staff Member' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: EDIT STAFF & PERMISSIONS -->
<!-- ========================================================================= -->
<div id="editUserModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-lg w-full p-6 shadow-2xl space-y-4 my-8">
        
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-500 flex items-center justify-center font-bold">
                    <i data-lucide="edit-3" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-black text-slate-900 dark:text-white text-base"><?= $isUrdu ? 'اسٹاف اور اختیارات ایڈٹ کریں' : 'Edit Staff & Permissions' ?></h3>
                    <p class="text-xs text-slate-400" id="editModalSubText">User: Name</p>
                </div>
            </div>
            <button type="button" onclick="closeEditUserModal()" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800">✕</button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="editUserId">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1"><?= $isUrdu ? 'اسٹاف نام *' : 'Name *' ?></label>
                    <input type="text" name="name" id="editUserName" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1"><?= $isUrdu ? 'موبائل فون' : 'Phone' ?></label>
                    <input type="text" name="phone" id="editUserPhone" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1"><?= $isUrdu ? 'عہدہ / رول *' : 'Role *' ?></label>
                    <select name="role" id="editUserRole" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none">
                        <option value="Cashier">Cashier</option>
                        <option value="Manager">Manager</option>
                        <option value="Salesperson">Salesperson</option>
                        <option value="Owner">Owner</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1"><?= $isUrdu ? 'سیکیورٹی پن' : 'PIN Code' ?></label>
                    <input type="text" name="pin" id="editUserPin" maxlength="6" class="w-full px-3 py-2 text-xs font-mono font-black rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1"><?= $isUrdu ? 'اسٹیٹس' : 'Status' ?></label>
                    <select name="status" id="editUserStatus" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none">
                        <option value="ACTIVE">ACTIVE</option>
                        <option value="SUSPENDED">SUSPENDED</option>
                    </select>
                </div>
            </div>

            <!-- Granular Permissions Selector -->
            <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <label class="block text-xs font-black text-slate-800 dark:text-slate-200">
                    <?= $isUrdu ? 'سافٹ ویئر کے اختیارات (Module Permissions):' : 'Module Permissions:' ?>
                </label>
                
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="pos" id="perm_pos" class="rounded text-blue-600 focus:ring-0">
                        <span>🛒 POS Billing</span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="inventory" id="perm_inventory" class="rounded text-blue-600 focus:ring-0">
                        <span>📦 Stock</span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="purchases" id="perm_purchases" class="rounded text-blue-600 focus:ring-0">
                        <span>📱 Purchases</span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="khata" id="perm_khata" class="rounded text-blue-600 focus:ring-0">
                        <span>👥 Khata</span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="easypaisa" id="perm_easypaisa" class="rounded text-blue-600 focus:ring-0">
                        <span>💵 EasyPaisa</span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="barcode" id="perm_barcode" class="rounded text-blue-600 focus:ring-0">
                        <span>🏷️ Barcodes</span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="reports" id="perm_reports" class="rounded text-blue-600 focus:ring-0">
                        <span>📊 Reports</span>
                    </label>

                    <label class="flex items-center gap-2 p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/60 cursor-pointer">
                        <input type="checkbox" name="permissions[]" value="photo_vault" id="perm_photo_vault" class="rounded text-blue-600 focus:ring-0">
                        <span>📷 Vault</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeEditUserModal()" class="px-4 py-2 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800">
                    <?= $isUrdu ? 'منسوخ' : 'Cancel' ?>
                </button>
                <button type="submit" class="px-5 py-2 text-xs font-black rounded-xl bg-blue-600 hover:bg-blue-500 text-white shadow-md">
                    <?= $isUrdu ? 'تبدیلیاں محفوظ کریں' : 'Save Changes' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: RESET PASSWORD -->
<!-- ========================================================================= -->
<div id="resetPassModal" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl max-w-sm w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
            <h3 class="font-black text-slate-900 dark:text-white text-base"><?= $isUrdu ? 'نیا پاسورڈ درج کریں' : 'Reset Staff Password' ?></h3>
            <button type="button" onclick="closeResetPassModal()" class="p-1 rounded-lg text-slate-400 hover:text-white">✕</button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetUserId">
            <input type="hidden" name="user_name" id="resetUserName">

            <p class="text-xs text-slate-500" id="resetUserPrompt">Enter new password for user.</p>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1"><?= $isUrdu ? 'نیا پاسورڈ *' : 'New Password *' ?></label>
                <input type="password" name="new_password" required minlength="4" placeholder="••••••" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-white focus:outline-none focus:border-amber-500">
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                <button type="button" onclick="closeResetPassModal()" class="px-4 py-2 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                    <?= $isUrdu ? 'منسوخ' : 'Cancel' ?>
                </button>
                <button type="submit" class="px-4 py-2 text-xs font-black rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-md">
                    <?= $isUrdu ? 'پاسورڈ تبدیل کریں' : 'Update Password' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Tab Switching
    function switchStaffTab(tab) {
        const tabs = ['staff', 'permissions', 'logs'];
        tabs.forEach(t => {
            const content = document.getElementById('tabContent' + t.charAt(0).toUpperCase() + t.slice(1));
            const btn = document.getElementById('tabBtn' + (t === 'permissions' ? 'Perms' : t.charAt(0).toUpperCase() + t.slice(1)));
            if (content && btn) {
                if (t === tab) {
                    content.classList.remove('hidden');
                    btn.className = "px-4 py-2.5 rounded-xl bg-emerald-600 text-white shadow-md transition-all flex items-center gap-2 shrink-0 cursor-pointer";
                } else {
                    content.classList.add('hidden');
                    btn.className = "px-4 py-2.5 rounded-xl text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all flex items-center gap-2 shrink-0 cursor-pointer";
                }
            }
        });
    }

    // Filter Table
    function filterStaffTable() {
        const q = document.getElementById('userSearchInput').value.toLowerCase();
        const role = document.getElementById('roleFilterSelect').value;
        const rows = document.querySelectorAll('.user-row');

        rows.forEach(r => {
            const rRole = r.getAttribute('data-role');
            const rSearch = r.getAttribute('data-search') || '';
            const matchRole = (role === 'ALL' || rRole === role);
            const matchSearch = rSearch.includes(q);

            if (matchRole && matchSearch) {
                r.style.display = '';
            } else {
                r.style.display = 'none';
            }
        });
    }

    // Add Staff Modal
    function openAddUserModal() {
        document.getElementById('addUserModal').classList.remove('hidden');
    }
    function closeAddUserModal() {
        document.getElementById('addUserModal').classList.add('hidden');
    }

    // Role preset permissions helper
    function autoCheckPermissions(mode) {
        if (mode === 'add') {
            const role = document.getElementById('addRoleSelect').value;
            const perms = document.querySelectorAll('#addPermsContainer input[type="checkbox"]');
            perms.forEach(p => {
                if (role === 'Cashier') {
                    p.checked = (p.value === 'pos' || p.value === 'easypaisa' || p.value === 'barcode');
                } else if (role === 'Manager') {
                    p.checked = (p.value !== 'photo_vault');
                } else if (role === 'Salesperson') {
                    p.checked = (p.value === 'pos' || p.value === 'barcode');
                }
            });
        }
    }

    // Edit Staff Modal
    function openEditUserModal(user) {
        document.getElementById('editUserId').value = user.id || '';
        document.getElementById('editUserName').value = user.name || '';
        document.getElementById('editUserPhone').value = user.phone || '';
        document.getElementById('editUserRole').value = user.role || 'Cashier';
        document.getElementById('editUserPin').value = user.pin_code || '';
        document.getElementById('editUserStatus').value = user.status || 'ACTIVE';
        document.getElementById('editModalSubText').textContent = user.email || '';

        // Uncheck all perms
        const allPermBoxes = ['pos', 'inventory', 'purchases', 'khata', 'easypaisa', 'barcode', 'reports', 'photo_vault'];
        allPermBoxes.forEach(p => {
            const el = document.getElementById('perm_' + p);
            if (el) el.checked = false;
        });

        // Check user's permissions
        try {
            const userPerms = (typeof user.permissions === 'string') ? JSON.parse(user.permissions) : (user.permissions || []);
            if (Array.isArray(userPerms)) {
                userPerms.forEach(p => {
                    const el = document.getElementById('perm_' + p);
                    if (el) el.checked = true;
                });
                if (userPerms.includes('all')) {
                    allPermBoxes.forEach(p => {
                        const el = document.getElementById('perm_' + p);
                        if (el) el.checked = true;
                    });
                }
            }
        } catch(e) {}

        document.getElementById('editUserModal').classList.remove('hidden');
    }
    function closeEditUserModal() {
        document.getElementById('editUserModal').classList.add('hidden');
    }

    // Reset Password Modal
    function openResetPassModal(id, name) {
        document.getElementById('resetUserId').value = id;
        document.getElementById('resetUserName').value = name;
        document.getElementById('resetUserPrompt').textContent = "<?= $isUrdu ? 'صارف برائے نیا پاسورڈ درج کریں:' : 'Set new password for:' ?> " + name;
        document.getElementById('resetPassModal').classList.remove('hidden');
    }
    function closeResetPassModal() {
        document.getElementById('resetPassModal').classList.add('hidden');
    }

    // Close on backdrop click
    window.onclick = function(e) {
        if (e.target === document.getElementById('addUserModal')) closeAddUserModal();
        if (e.target === document.getElementById('editUserModal')) closeEditUserModal();
        if (e.target === document.getElementById('resetPassModal')) closeResetPassModal();
    }
</script>

<?php require_once __DIR__ . '/../backend/footer.php'; ?>
