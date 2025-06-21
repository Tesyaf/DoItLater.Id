<?php
$page_title = 'Admin Dashboard';
require_once '../config/database.php';
require_once '../config/session.php';

requireAdmin(); // Pastikan hanya admin yang bisa mengakses halaman ini

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$message = '';
$message_type = ''; // 'success' or 'error'

// Ambil pesan flash jika ada
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message']['text'];
    $message_type = $_SESSION['flash_message']['type'];
    unset($_SESSION['flash_message']); // Hapus pesan setelah ditampilkan
}

// --- LOGIKA CRUD TIM ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Create Team
    if (isset($_POST['create_team'])) {
        $nama_team = trim($_POST['nama_team']);
        if (!empty($nama_team)) {
            try {
                $db->beginTransaction();
                $query_team = "INSERT INTO tbl_team (nama_team, id_pembuat) VALUES (?, ?)";
                $stmt_team = $db->prepare($query_team);
                $stmt_team->execute([$nama_team, $current_user['id']]);
                $new_team_id = $db->lastInsertId();

                $team_code = 'TEAM' . str_pad($new_team_id, 4, '0', STR_PAD_LEFT);
                $query_code = "UPDATE tbl_team SET team_code = ? WHERE id_team = ?";
                $stmt_code = $db->prepare($query_code);
                $stmt_code->execute([$team_code, $new_team_id]);

                $query_member = "INSERT INTO tbl_team_members (id_team, id_user, role_tim) VALUES (?, ?, 'owner')";
                $stmt_member = $db->prepare($query_member);
                $stmt_member->execute([$new_team_id, $current_user['id']]);

                $db->commit();
                $message = 'Tim "' . htmlspecialchars($nama_team) . '" berhasil dibuat!';
                $message_type = 'success';
            } catch (Exception $e) {
                $db->rollBack();
                $message = 'Error membuat tim: ' . $e->getMessage();
                $message_type = 'error';
                error_log("Error creating team: " . $e->getMessage());
            }
        } else {
            $message = 'Nama tim tidak boleh kosong.';
            $message_type = 'error';
        }
    }

    // Update Team
    if (isset($_POST['update_team'])) {
        $id_team = $_POST['id_team'];
        $nama_team = trim($_POST['nama_team']);
        if (!empty($nama_team) && is_numeric($id_team)) {
            try {
                $query = "UPDATE tbl_team SET nama_team = ? WHERE id_team = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$nama_team, $id_team]);
                $message = 'Tim berhasil diperbarui!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error memperbarui tim: ' . $e->getMessage();
                $message_type = 'error';
                error_log("Error updating team: " . $e->getMessage());
            }
        } else {
            $message = 'Input tidak valid untuk pembaruan tim.';
            $message_type = 'error';
        }
    }

    // Delete Team
    if (isset($_POST['delete_team'])) {
        $id_team = $_POST['id_team'];
        if (is_numeric($id_team)) {
            try {
                $db->beginTransaction();
                $stmt_members = $db->prepare("DELETE FROM tbl_team_members WHERE id_team = ?");
                $stmt_members->execute([$id_team]);
                $stmt_ongoing = $db->prepare("DELETE FROM tbl_ongoing WHERE id_team = ?");
                $stmt_ongoing->execute([$id_team]);
                $stmt_done = $db->prepare("DELETE FROM tbl_done WHERE id_team = ?");
                $stmt_done->execute([$id_team]);
                $query = "DELETE FROM tbl_team WHERE id_team = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id_team]);
                $db->commit();
                $message = 'Tim berhasil dihapus!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $db->rollBack();
                $message = 'Error menghapus tim: ' . $e->getMessage();
                $message_type = 'error';
                error_log("Error deleting team: " . $e->getMessage());
            }
        } else {
            $message = 'ID tim tidak valid untuk penghapusan.';
            $message_type = 'error';
        }
    }

    // --- LOGIKA CRUD PENGGUNA ---
    // Create User
    if (isset($_POST['create_user'])) {
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role_sistem = $_POST['role_sistem']; // Sesuaikan dengan nama kolom di DB Anda

        if (!empty($nama_lengkap) && !empty($email) && !empty($password) && !empty($role_sistem)) {
            $check_email_query = "SELECT COUNT(*) FROM tbl_user WHERE email = ?";
            $stmt_check = $db->prepare($check_email_query);
            $stmt_check->execute([$email]);
            if ($stmt_check->fetchColumn() > 0) {
                $message = 'Error: Email sudah terdaftar.';
                $message_type = 'error';
            } else {
                try {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    // Pastikan nama kolom 'role_sistem' di sini
                    $query = "INSERT INTO tbl_user (nama_lengkap, email, password, role_sistem) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$nama_lengkap, $email, $hashed_password, $role_sistem]);
                    $message = 'Pengguna "' . htmlspecialchars($nama_lengkap) . '" berhasil dibuat!';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Error membuat pengguna: ' . $e->getMessage();
                    $message_type = 'error';
                    error_log("Error creating user: " . $e->getMessage());
                }
            }
        } else {
            $message = 'Semua kolom wajib diisi untuk membuat pengguna.';
            $message_type = 'error';
        }
    }

    // Update User
    if (isset($_POST['update_user'])) {
        $id_user = $_POST['id_user'];
        $nama_lengkap = trim($_POST['nama_lengkap']);
        $email = trim($_POST['email']);
        $role_sistem = $_POST['role_sistem']; // Sesuaikan dengan nama kolom di DB Anda
        $password_change = !empty($_POST['password']);

        if (!empty($nama_lengkap) && !empty($email) && !empty($role_sistem) && is_numeric($id_user)) {
            try {
                // Pastikan nama kolom 'role_sistem' di sini
                $query = "UPDATE tbl_user SET nama_lengkap = ?, email = ?, role_sistem = ? WHERE id_user = ?";
                $params = [$nama_lengkap, $email, $role_sistem, $id_user];

                if ($password_change) {
                    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    // Pastikan nama kolom 'role_sistem' di sini
                    $query = "UPDATE tbl_user SET nama_lengkap = ?, email = ?, password = ?, role_sistem = ? WHERE id_user = ?";
                    $params = [$nama_lengkap, $email, $hashed_password, $role_sistem, $id_user];
                }

                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $message = 'Pengguna berhasil diperbarui!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error memperbarui pengguna: ' . $e->getMessage();
                $message_type = 'error';
                error_log("Error updating user: " . $e->getMessage());
            }
        } else {
            $message = 'Input tidak valid untuk pembaruan pengguna.';
            $message_type = 'error';
        }
    }

   // Delete User
    if (isset($_POST['delete_user'])) {
        $id_user_delete = $_POST['id_user'];
        if ($id_user_delete == $current_user['id']) {
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Anda tidak dapat menghapus akun admin Anda sendiri.'];
        } else if (is_numeric($id_user_delete)) {
            try {
                $db->beginTransaction();

                // 1. Hapus pengguna dari semua tim yang dia anggota
                $stmt_team_members = $db->prepare("DELETE FROM tbl_team_members WHERE id_user = ?");
                $stmt_team_members->execute([$id_user_delete]);

                // 2. Perbarui tugas yang ditugaskan kepada pengguna ini menjadi NULL
                // (Asumsi id_penanggung_jawab bisa NULL, jika tidak, ini juga perlu DELETE)
                $stmt_ongoing_tasks = $db->prepare("UPDATE tbl_ongoing SET id_penanggung_jawab = NULL WHERE id_penanggung_jawab = ?");
                $stmt_ongoing_tasks->execute([$id_user_delete]);

                // 3. Hapus tugas selesai yang diselesaikan oleh pengguna ini
                // Perbaikan: Mengubah UPDATE menjadi DELETE karena id_penyelesai tidak bisa NULL
                $stmt_done_tasks = $db->prepare("DELETE FROM tbl_done WHERE id_penyelesai = ?");
                $stmt_done_tasks->execute([$id_user_delete]);

                // 4. Hapus tim yang dibuat oleh pengguna ini
                // Perbaikan: Mengubah UPDATE menjadi DELETE karena id_pembuat tidak bisa NULL
                $delete_team_owner_query = "DELETE FROM tbl_team WHERE id_pembuat = ?";
                $stmt_delete_team_owner = $db->prepare($delete_team_owner_query);
                $stmt_delete_team_owner->execute([$id_user_delete]);

                // 5. Akhirnya, hapus pengguna dari tabel tbl_user
                $query_delete_user = "DELETE FROM tbl_user WHERE id_user = ?";
                $stmt_delete_user = $db->prepare($query_delete_user);
                $stmt_delete_user->execute([$id_user_delete]);

                $db->commit();
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Pengguna berhasil dihapus!'];
            } catch (PDOException $e) {
                $db->rollBack();
                $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'Error menghapus pengguna: ' . $e->getMessage()];
                error_log("Error deleting user: " . $e->getMessage());
            }
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => 'ID pengguna tidak valid untuk penghapusan.'];
        }
        header('Location: admin_dashboard.php#users-section'); // Redirect to users tab after deletion
        exit();
    }
}

// --- Ambil Data untuk Tampilan ---
$teams_query = "SELECT t.*, u.nama_lengkap as pembuat_nama FROM tbl_team t LEFT JOIN tbl_user u ON t.id_pembuat = u.id_user ORDER BY t.created_at DESC";
$stmt_teams = $db->prepare($teams_query);
$stmt_teams->execute();
$teams = $stmt_teams->fetchAll(PDO::FETCH_ASSOC);

// Pastikan nama kolom 'role_sistem' di sini
$users_query = "SELECT id_user, nama_lengkap, email, role_sistem, created_at FROM tbl_user ORDER BY created_at DESC";
$stmt_users = $db->prepare($users_query);
$stmt_users->execute();
$users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

include 'includes/admin_header.php'; // Menggunakan admin_header.php
include 'includes/admin_navbar.php'; // Menggunakan admin_navbar.php
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Dashboard Admin</h1>
        <p class="text-gray-600">Kelola tim dan pengguna dengan mudah</p>
    </div>

    <?php if ($message): ?>
        <div class="p-4 mb-4 text-sm rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="mb-6 border-b-2 border-purple-200">
        <ul class="flex flex-wrap -mb-px text-sm font-medium text-center" id="adminTabs" role="tablist">
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 border-b-2 rounded-t-lg text-purple-600 border-purple-600" id="teams-tab" data-tabs-target="#teams-section" type="button" role="tab" aria-controls="teams-section" aria-selected="true">Kelola Tim</button>
            </li>
            <li class="mr-2" role="presentation">
                <button class="inline-block p-4 border-b-2 border-transparent rounded-t-lg hover:text-purple-600 hover:border-purple-300" id="users-tab" data-tabs-target="#users-section" type="button" role="tab" aria-controls="users-section" aria-selected="false">Kelola Pengguna</button>
            </li>
        </ul>
    </div>

    <div id="adminTabContent">
        <div class="p-4 rounded-2xl bg-white shadow-lg active" id="teams-section" role="tabpanel" aria-labelledby="teams-tab">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Tim</h2>

            <div class="bg-gray-50 rounded-lg p-6 mb-8 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Buat Tim Baru</h3>
                <form method="POST" class="flex flex-col sm:flex-row gap-4">
                    <input type="text" name="nama_team" required
                           class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200"
                           placeholder="Masukkan nama tim baru...">
                    <button type="submit" name="create_team"
                            class="bg-gradient-to-r from-purple-500 to-blue-500 text-white px-6 py-3 rounded-lg hover:from-purple-600 hover:to-blue-600 transition-all duration-200 font-medium">
                        Buat Tim
                    </button>
                </form>
            </div>

            <div class="bg-gray-50 rounded-lg shadow-inner p-6 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Daftar Tim</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Nama Tim</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Kode Tim</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Dibuat Oleh</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($teams)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-center text-gray-500">Tidak ada tim ditemukan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($teams as $team): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-700"><?php echo htmlspecialchars($team['id_team']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium"><?php echo htmlspecialchars($team['nama_team']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($team['team_code']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($team['pembuat_nama'] ?: 'N/A'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex gap-2">
                                            <button onclick="openEditTeamModal(<?php echo htmlspecialchars(json_encode($team)); ?>)" class="text-purple-600 hover:text-purple-800 transition-colors duration-200">Edit</button>
                                            <button type="button" onclick="openDeleteTeamModal(<?php echo htmlspecialchars($team['id_team']); ?>, '<?php echo htmlspecialchars($team['nama_team']); ?>')" class="text-red-600 hover:text-red-800 ml-4 transition-colors duration-200">Hapus</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="hidden p-4 rounded-2xl bg-white shadow-lg" id="users-section" role="tabpanel" aria-labelledby="users-tab">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Pengguna</h2>

            <div class="bg-gray-50 rounded-lg p-6 mb-8 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Buat Pengguna Baru</h3>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="new_nama_lengkap" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                        <input type="text" id="new_nama_lengkap" name="nama_lengkap" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-transparent sm:text-sm">
                    </div>
                    <div>
                        <label for="new_email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" id="new_email" name="email" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-transparent sm:text-sm">
                    </div>
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700">Kata Sandi</label>
                        <input type="password" id="new_password" name="password" required
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-transparent sm:text-sm">
                    </div>
                    <div>
                        <label for="new_role_sistem" class="block text-sm font-medium text-gray-700">Peran</label>
                        <select id="new_role_sistem" name="role_sistem" required
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-transparent sm:text-sm">
                            <option value="user">Pengguna</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-span-full">
                        <button type="submit" name="create_user"
                                class="w-full bg-gradient-to-r from-purple-500 to-blue-500 text-white py-3 px-4 rounded-lg hover:from-purple-600 hover:to-blue-600 transition-all duration-200 font-medium">
                            Buat Pengguna
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-gray-50 rounded-lg shadow-inner p-6 border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Daftar Pengguna</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Nama Lengkap</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Email</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Peran</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 whitespace-nowrap text-center text-gray-500">Tidak ada pengguna ditemukan.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-700"><?php echo htmlspecialchars($user['id_user']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-900 font-medium"><?php echo htmlspecialchars($user['nama_lengkap']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600"><?php echo htmlspecialchars($user['role_sistem']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex gap-2">
                                            <button onclick="openEditUserModal(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="text-purple-600 hover:text-purple-800 transition-colors duration-200">Edit</button>
                                            <button type="button" onclick="openDeleteUserModal(<?php echo htmlspecialchars($user['id_user']); ?>, '<?php echo htmlspecialchars($user['nama_lengkap']); ?>', <?php echo $user['id_user'] == $current_user['id'] ? 'true' : 'false'; ?>)" class="text-red-600 hover:text-red-800 ml-4 transition-colors duration-200">Hapus</button>
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
</div>

<div id="editTeamModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Edit Tim</h3>
        <form method="POST">
            <input type="hidden" id="edit_team_id" name="id_team">
            <div class="mb-4">
                <label for="edit_nama_team" class="block text-sm font-medium text-gray-700">Nama Tim</label>
                <input type="text" id="edit_nama_team" name="nama_team" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-transparent sm:text-sm">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeEditTeamModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Batal</button>
                <button type="submit" name="update_team" class="px-4 py-2 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-md hover:from-purple-600 hover:to-blue-600">Perbarui</button>
            </div>
        </form>
    </div>
</div>

<!-- Custom Delete Team Confirmation Modal -->
<div id="deleteTeamConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Konfirmasi Hapus Tim</h3>
        <p class="text-gray-700 mb-4">Apakah Anda yakin ingin menghapus tim "<strong id="delete_team_name_confirm"></strong>" beserta semua data terkait (tugas, anggota)?</p>
        <form method="POST" id="deleteTeamForm">
            <input type="hidden" id="delete_team_id_confirm" name="id_team">
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeDeleteTeamConfirmModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Batal</button>
                <button type="submit" name="delete_team" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Hapus</button>
            </div>
        </form>
    </div>
</div>


<div id="editUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Edit Pengguna</h3>
        <form method="POST">
            <input type="hidden" id="edit_user_id" name="id_user">
            <div class="mb-4">
                <label for="edit_nama_lengkap" class="block text-sm font-medium text-gray-700">Nama Lengkap</label>
                <input type="text" id="edit_nama_lengkap" name="nama_lengkap" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-transparent sm:text-sm">
            </div>
            <div class="mb-4">
                <label for="edit_email" class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" id="edit_email" name="email" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-transparent sm:text-sm">
            </div>
            <div class="mb-4">
                <label for="edit_password" class="block text-sm font-medium text-gray-700">Kata Sandi Baru (kosongkan)</label>
                <input type="password" id="edit_password" name="password"
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-transparent sm:text-sm">
            </div>
            <div class="mb-4">
                <label for="edit_role_sistem" class="block text-sm font-medium text-gray-700">Peran</label>
                <select id="edit_role_sistem" name="role_sistem" required
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-transparent sm:text-sm">
                    <option value="user">Pengguna</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeEditUserModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Batal</button>
                <button type="submit" name="update_user" class="px-4 py-2 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-md hover:from-purple-600 hover:to-blue-600">Perbarui</button>
            </div>
        </form>
    </div>
</div>

<!-- Custom Delete User Confirmation Modal -->
<div id="deleteUserConfirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Konfirmasi Hapus Pengguna</h3>
        <p class="text-gray-700 mb-4">Apakah Anda yakin ingin menghapus pengguna "<strong id="delete_user_name_confirm"></strong>" beserta semua data terkait?</p>
        <p id="delete_self_warning" class="text-red-600 text-sm mb-4 hidden">Anda tidak dapat menghapus akun admin Anda sendiri.</p>
        <form method="POST" id="deleteUserForm">
            <input type="hidden" id="delete_user_id_confirm" name="id_user">
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeDeleteUserConfirmModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Batal</button>
                <button type="submit" name="delete_user" id="confirmDeleteUserBtn" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">Hapus</button>
            </div>
        </form>
    </div>
</div>

<script>
    // JavaScript for Tab functionality
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('#adminTabs button');
        const tabContents = document.querySelectorAll('#adminTabContent > div');

        function activateTab(tabId) {
            tabs.forEach(tab => {
                if (tab.id === tabId) {
                    tab.classList.add('text-purple-600', 'border-purple-600');
                    tab.classList.remove('border-transparent', 'hover:text-purple-600', 'hover:border-purple-300');
                    tab.setAttribute('aria-selected', 'true');
                } else {
                    tab.classList.remove('text-purple-600', 'border-purple-600');
                    tab.classList.add('border-transparent', 'hover:text-purple-600', 'hover:border-purple-300');
                    tab.setAttribute('aria-selected', 'false');
                }
            });

            tabContents.forEach(content => {
                if (content.id === tabId.replace('-tab', '-section')) {
                    content.classList.remove('hidden');
                    content.classList.add('active');
                } else {
                    content.classList.add('hidden');
                    content.classList.remove('active');
                }
            });
        }

        // Check for URL hash to activate specific tab, otherwise activate first tab
        const urlHash = window.location.hash;
        if (urlHash) {
            const targetTabId = urlHash.substring(1) + '-tab'; // e.g., #users-section -> users-section-tab
            const initialTab = document.getElementById(targetTabId);
            if (initialTab) {
                activateTab(targetTabId);
            } else {
                activateTab('teams-tab'); // Fallback to default
            }
        } else {
            activateTab('teams-tab'); // Activate first tab by default
        }


        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                activateTab(this.id);
                // Update URL hash without reloading
                window.history.pushState(null, '', '#' + this.dataset.tabsTarget.substring(1));
            });
        });
    });

    // JavaScript for Team Edit Modal
    function openEditTeamModal(team) {
        document.getElementById('edit_team_id').value = team.id_team;
        document.getElementById('edit_nama_team').value = team.nama_team;
        document.getElementById('editTeamModal').classList.remove('hidden');
    }

    function closeEditTeamModal() {
        document.getElementById('editTeamModal').classList.add('hidden');
    }

    // JavaScript for Team Delete Confirmation Modal
    function openDeleteTeamModal(teamId, teamName) {
        document.getElementById('delete_team_id_confirm').value = teamId;
        document.getElementById('delete_team_name_confirm').innerText = teamName;
        document.getElementById('deleteTeamConfirmModal').classList.remove('hidden');
    }

    function closeDeleteTeamConfirmModal() {
        document.getElementById('deleteTeamConfirmModal').classList.add('hidden');
    }


    // JavaScript for User Edit Modal
    function openEditUserModal(user) {
        document.getElementById('edit_user_id').value = user.id_user;
        document.getElementById('edit_nama_lengkap').value = user.nama_lengkap;
        document.getElementById('edit_email').value = user.email;
        // Pastikan menggunakan 'edit_role_sistem' di sini
        document.getElementById('edit_role_sistem').value = user.role_sistem;
        document.getElementById('edit_password').value = ''; // Clear password field for security
        document.getElementById('editUserModal').classList.remove('hidden');
    }

    function closeEditUserModal() {
        document.getElementById('editUserModal').classList.add('hidden');
    }

    // JavaScript for User Delete Confirmation Modal
    function openDeleteUserModal(userId, userName, isCurrentUser) {
        document.getElementById('delete_user_id_confirm').value = userId;
        document.getElementById('delete_user_name_confirm').innerText = userName;
        
        const deleteSelfWarning = document.getElementById('delete_self_warning');
        const confirmDeleteUserBtn = document.getElementById('confirmDeleteUserBtn');

        if (isCurrentUser) {
            deleteSelfWarning.classList.remove('hidden');
            confirmDeleteUserBtn.disabled = true; // Nonaktifkan tombol hapus
            confirmDeleteUserBtn.classList.add('opacity-50', 'cursor-not-allowed'); // Tambahkan gaya disabled
        } else {
            deleteSelfWarning.classList.add('hidden');
            confirmDeleteUserBtn.disabled = false; // Aktifkan tombol hapus
            confirmDeleteUserBtn.classList.remove('opacity-50', 'cursor-not-allowed'); // Hapus gaya disabled
        }
        
        document.getElementById('deleteUserConfirmModal').classList.remove('hidden');
    }

    function closeDeleteUserConfirmModal() {
        document.getElementById('deleteUserConfirmModal').classList.add('hidden');
    }
</script>

<?php include 'includes/admin_footer.php'; // Menggunakan admin_footer.php ?>
