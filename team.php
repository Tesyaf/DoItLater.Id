<?php
$page_title = 'Tim';
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$team_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$team_id) {
    header('Location: dashboard.php');
    exit();
}

// Check if user is member of this team AND get their role_tim for this team
$query = "SELECT t.*, u.nama_lengkap as pembuat_nama, tm.role_tim
          FROM tbl_team t
          LEFT JOIN tbl_user u ON t.id_pembuat = u.id_user
          INNER JOIN tbl_team_members tm ON t.id_team = tm.id_team
          WHERE t.id_team = ? AND tm.id_user = ?";
$stmt = $db->prepare($query);
$stmt->execute([$team_id, $current_user['id']]);
$team = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$team) {
    header('Location: dashboard.php');
    exit();
}

// Determine if current user is the owner of THIS team
$is_team_owner = ($team['role_tim'] === 'owner');

// Initialize message variables
$message = '';
$message_type = ''; // 'success' or 'error'

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_task'])) {
        $task_id = (int)$_POST['edit_task_id'];
        $judul = trim($_POST['edit_judul_tugas']);
        $deskripsi = trim($_POST['edit_deskripsi']);
        $deadline = $_POST['edit_tanggal_deadline'];
        $kategori = !empty($_POST['edit_id_kategori']) ? (int)$_POST['edit_id_kategori'] : null;
        $penanggung_jawab = !empty($_POST['edit_id_penanggung_jawab']) ? (int)$_POST['edit_id_penanggung_jawab'] : null;
    
        if (!empty($judul)) {
            try {
                $query = "UPDATE tbl_ongoing 
                          SET judul_tugas = ?, deskripsi = ?, tanggal_deadline = ?, id_kategori = ?, id_penanggung_jawab = ?
                          WHERE id_tugas = ? AND id_team = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$judul, $deskripsi, $deadline, $kategori, $penanggung_jawab, $task_id, $team_id]);
    
                $message = "Tugas berhasil diperbarui!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Gagal memperbarui tugas: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Judul tugas tidak boleh kosong.";
            $message_type = "error";
        }
    
        header("Location: team.php?id=$team_id");
        exit();
    }
     
    if (isset($_POST['add_task'])) {
        $judul = trim($_POST['judul_tugas']);
        $deskripsi = trim($_POST['deskripsi']);
        $kategori = !empty($_POST['id_kategori']) ? (int)$_POST['id_kategori'] : null;
        $penanggung_jawab = !empty($_POST['id_penanggung_jawab']) ? (int)$_POST['id_penanggung_jawab'] : null;
        $deadline = $_POST['tanggal_deadline'];

        if (!empty($judul)) {
            try {
                $query = "INSERT INTO tbl_ongoing (id_team, id_kategori, id_penanggung_jawab, judul_tugas, deskripsi, tanggal_deadline)
                          VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$team_id, $kategori, $penanggung_jawab, $judul, $deskripsi, $deadline]);
                $message = "Tugas berhasil ditambahkan!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Gagal menambahkan tugas: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Judul tugas tidak boleh kosong.";
            $message_type = "error";
        }
    } elseif (isset($_POST['complete_task'])) {
        $task_id = (int)$_POST['task_id'];

        try {
            // Get task details
            $query = "SELECT * FROM tbl_ongoing WHERE id_tugas = ? AND id_team = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$task_id, $team_id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($task) {
                // Move to done table
                $query = "INSERT INTO tbl_done (id_tugas_asli, id_team, judul_tugas, id_penyelesai)
                          VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$task_id, $team_id, $task['judul_tugas'], $current_user['id']]);

                // Delete from ongoing
                $query = "DELETE FROM tbl_ongoing WHERE id_tugas = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$task_id]);
                $message = "Tugas berhasil diselesaikan!";
                $message_type = "success";
            } else {
                $message = "Tugas tidak ditemukan atau bukan milik tim ini.";
                $message_type = "error";
            }
        } catch (PDOException $e) {
            $message = "Gagal menyelesaikan tugas: " . $e->getMessage();
            $message_type = "error";
        }
        header("Location: team.php?id=$team_id");
        exit();
    } elseif (isset($_POST['add_category'])) {
        $nama_kategori = trim($_POST['nama_kategori']);

        if (!empty($nama_kategori)) {
            try {
                $query = "INSERT INTO tbl_kategori (id_team, nama_kategori) VALUES (?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$team_id, $nama_kategori]);
                $message = "Kategori berhasil ditambahkan!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Gagal menambahkan kategori: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Nama kategori tidak boleh kosong.";
            $message_type = "error";
        }
    }elseif (isset($_POST['delete_category']) && isset($_POST['delete_kategori_id'])) {
        $kategori_id = (int)$_POST['delete_kategori_id'];
    
        try {
            // Optional: Check if any task is using this category first
            $check_query = "SELECT COUNT(*) FROM tbl_ongoing WHERE id_kategori = ?";
            $stmt_check = $db->prepare($check_query);
            $stmt_check->execute([$kategori_id]);
            $used_count = $stmt_check->fetchColumn();
    
            if ($used_count > 0) {
                $message = "Kategori tidak dapat dihapus karena masih digunakan oleh tugas.";
                $message_type = "error";
            } else {
                // Delete the category
                $delete_query = "DELETE FROM tbl_kategori WHERE id_kategori = ? AND id_team = ?";
                $stmt_delete = $db->prepare($delete_query);
                $stmt_delete->execute([$kategori_id, $team_id]);
    
                $message = "Kategori berhasil dihapus!";
                $message_type = "success";
            }
        } catch (PDOException $e) {
            $message = "Gagal menghapus kategori: " . $e->getMessage();
            $message_type = "error";
        }    
    }elseif (isset($_POST['edit_category']) && isset($_POST['edit_kategori_id'])) {
        $kategori_id = (int)$_POST['edit_kategori_id'];
        $new_name = trim($_POST['new_nama_kategori']);
    
        if (!empty($new_name)) {
            try {
                $query = "UPDATE tbl_kategori SET nama_kategori = ? WHERE id_kategori = ? AND id_team = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$new_name, $kategori_id, $team_id]);
                $message = "Kategori berhasil diperbarui!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Gagal memperbarui kategori: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Nama kategori tidak boleh kosong.";
            $message_type = "error";
        }   
    } elseif (isset($_POST['delete_task_id'])) {
        $task_id = (int) $_POST['delete_task_id'];
        $source = $_POST['source'] ?? 'ongoing'; // 'ongoing' or 'done'
    
        try {
            if ($source === 'ongoing') {
                $stmt = $db->prepare("DELETE FROM tbl_ongoing WHERE id_tugas = ? AND id_team = ?");
            } else {
                $stmt = $db->prepare("DELETE FROM tbl_done WHERE id_tugas_asli = ? AND id_team = ?");
            }
    
            $stmt->execute([$task_id, $team_id]);
            $message = "Tugas berhasil dihapus.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Gagal menghapus tugas: " . $e->getMessage();
            $message_type = "error";
        }
    
        header("Location: team.php?id=$team_id");
        exit();
    }
     // --- OWNER-SPECIFIC LOGIC ---
    elseif ($is_team_owner && isset($_POST['update_team_name'])) {
        $new_team_name = trim($_POST['new_nama_team']);
        if (!empty($new_team_name)) {
            try {
                $query = "UPDATE tbl_team SET nama_team = ? WHERE id_team = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$new_team_name, $team_id]);
                $team['nama_team'] = $new_team_name; // Update team array for immediate display
                $message = "Nama tim berhasil diperbarui!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Gagal memperbarui nama tim: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Nama tim tidak boleh kosong.";
            $message_type = "error";
        }
        header("Location: team.php?id=$team_id");
        exit();
    } elseif ($is_team_owner && isset($_POST['remove_member'])) {
        $member_id_to_remove = (int)$_POST['member_id_to_remove'];
        if ($member_id_to_remove === $current_user['id']) {
            $message = "Anda tidak bisa menghapus diri sendiri dari tim!";
            $message_type = "error";
        } else {
            try {
                // First, check if the member is actually in the team
                $check_member_query = "SELECT COUNT(*) FROM tbl_team_members WHERE id_team = ? AND id_user = ?";
                $stmt_check_member = $db->prepare($check_member_query);
                $stmt_check_member->execute([$team_id, $member_id_to_remove]);

                if ($stmt_check_member->fetchColumn() > 0) {
                    // Delete from tbl_team_members
                    $query = "DELETE FROM tbl_team_members WHERE id_team = ? AND id_user = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$team_id, $member_id_to_remove]);

                    // Optional: Reassign tasks from the removed member or set them to NULL
                    $update_tasks_query = "UPDATE tbl_ongoing SET id_penanggung_jawab = NULL WHERE id_team = ? AND id_penanggung_jawab = ?";
                    $stmt_update_tasks = $db->prepare($update_tasks_query);
                    $stmt_update_tasks->execute([$team_id, $member_id_to_remove]);

                    $message = "Anggota tim berhasil dihapus!";
                    $message_type = "success";
                } else {
                    $message = "Anggota tidak ditemukan dalam tim ini.";
                    $message_type = "error";
                }
            } catch (PDOException $e) {
                $message = "Gagal menghapus anggota: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
    // --- NEW LOGIC: Add Member by Email ---
    elseif ($is_team_owner && isset($_POST['add_member_by_email'])) {
        $member_email = trim($_POST['member_email']);
        $member_role = trim($_POST['member_role']); // 'member' or potentially 'admin' within team

        if (empty($member_email)) {
            $message = "Email anggota tidak boleh kosong.";
            $message_type = "error";
        } else {
            try {
                // 1. Find user by email
                $query_find_user = "SELECT id_user, nama_lengkap FROM tbl_user WHERE email = ?";
                $stmt_find_user = $db->prepare($query_find_user);
                $stmt_find_user->execute([$member_email]);
                $new_member_user = $stmt_find_user->fetch(PDO::FETCH_ASSOC);

                if ($new_member_user) {
                    $new_member_id = $new_member_user['id_user'];

                    // 2. Check if user is already a member of this team
                    $query_check_member = "SELECT COUNT(*) FROM tbl_team_members WHERE id_team = ? AND id_user = ?";
                    $stmt_check_member = $db->prepare($query_check_member);
                    $stmt_check_member->execute([$team_id, $new_member_id]);

                    if ($stmt_check_member->fetchColumn() > 0) {
                        $message = "Pengguna ini sudah menjadi anggota tim.";
                        $message_type = "error";
                    } else {
                        // 3. Add user to tbl_team_members
                        $query_add_member = "INSERT INTO tbl_team_members (id_team, id_user, role_tim) VALUES (?, ?, ?)";
                        $stmt_add_member = $db->prepare($query_add_member);
                        $stmt_add_member->execute([$team_id, $new_member_id, $member_role]);
                        $message = "Anggota " . htmlspecialchars($new_member_user['nama_lengkap']) . " berhasil ditambahkan!";
                        $message_type = "success";
                    }
                } else {
                    $message = "Tidak ada pengguna yang terdaftar dengan email tersebut.";
                    $message_type = "error";
                }
            } catch (PDOException $e) {
                $message = "Gagal menambahkan anggota: " . $e->getMessage();
                $message_type = "error";
                error_log("Error adding member: " . $e->getMessage());
            }
        }
    }  
    // --- END NEW LOGIC: Add Member by Email ---
    // Redirect to prevent form resubmission
    header("Location: team.php?id=$team_id");
    exit();
}

// Get filter parameters
$filter_kategori = isset($_GET['kategori']) ? (int)$_GET['kategori'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created';

// Get categories
$query = "SELECT * FROM tbl_kategori WHERE id_team = ? ORDER BY nama_kategori";
$stmt = $db->prepare($query);
$stmt->execute([$team_id]);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get team members for assignment AND for displaying in member list
// Importantly, select the role_tim for each member
$query = "SELECT u.id_user, u.nama_lengkap, tm.role_tim
          FROM tbl_user u
          INNER JOIN tbl_team_members tm ON u.id_user = tm.id_user
          WHERE tm.id_team = ?
          ORDER BY u.nama_lengkap";
$stmt = $db->prepare($query);
$stmt->execute([$team_id]);
$users_in_team = $stmt->fetchAll(PDO::FETCH_ASSOC); // Renamed to avoid conflict with $users for dropdown

// Build ongoing tasks query with filters
$where_clause = "WHERE o.id_team = ?";
$params = [$team_id];

if ($filter_kategori > 0) {
    $where_clause .= " AND o.id_kategori = ?";
    $params[] = $filter_kategori;
}

$order_clause = "ORDER BY ";
switch ($sort_by) {
    case 'deadline':
        $order_clause .= "o.tanggal_deadline ASC";
        break;
    case 'priority':
        // Tasks overdue first, then by earliest deadline
        $order_clause .= "CASE WHEN o.tanggal_deadline < CURDATE() THEN 1 ELSE 2 END, o.tanggal_deadline ASC";
        break;
    default: // 'created'
        $order_clause .= "o.created_at DESC";
}

$query = "SELECT o.*, k.nama_kategori, u.nama_lengkap as penanggung_jawab_nama,
          CASE WHEN o.tanggal_deadline < CURDATE() THEN 1 ELSE 0 END as is_overdue
          FROM tbl_ongoing o
          LEFT JOIN tbl_kategori k ON o.id_kategori = k.id_kategori
          LEFT JOIN tbl_user u ON o.id_penanggung_jawab = u.id_user
          $where_clause $order_clause";
$stmt = $db->prepare($query);
$stmt->execute($params);
$ongoing_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get done tasks
$query = "SELECT d.*, u.nama_lengkap as penyelesai_nama
          FROM tbl_done d
          LEFT JOIN tbl_user u ON d.id_penyelesai = u.id_user
          WHERE d.id_team = ?
          ORDER BY d.tanggal_selesai DESC";
$stmt = $db->prepare($query);
$stmt->execute([$team_id]);
$done_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-gradient-to-r from-purple-500 to-blue-500 rounded-2xl p-8 mb-8 text-white">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold mb-2"><?php echo htmlspecialchars($team['nama_team']); ?></h1>
                <p class="text-purple-100">Dibuat oleh: <?php echo htmlspecialchars($team['pembuat_nama']); ?></p>
            </div>
            <?php if ($is_team_owner): ?>
            <div>
                <button onclick="openEditTeamNameModal()" class="bg-white text-purple-600 px-4 py-2 rounded-lg hover:bg-gray-100 transition-colors duration-200 font-medium text-sm">
                    Edit Nama Tim
                </button>
            </div>
            <?php endif; ?>
        </div>
        <div class="mt-4 flex space-x-6">
            <div>
                <span class="text-2xl font-bold"><?php echo count($ongoing_tasks); ?></span>
                <span class="text-purple-100 ml-1">Tugas Aktif</span>
            </div>
            <div>
                <span class="text-2xl font-bold"><?php echo count($done_tasks); ?></span>
                <span class="text-purple-100 ml-1">Tugas Selesai</span>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="mb-4 p-4 text-sm rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 mb-2">Informasi Tim</h2>
                <p class="text-gray-600">Kode Tim: <span class="font-mono bg-gray-100 px-2 py-1 rounded"><?php echo htmlspecialchars($team['team_code'] ?? 'N/A'); ?></span></p>
                <p class="text-gray-600 mt-1">Role Anda: <span class="font-medium"><?php echo ucfirst($team['role_tim']); ?></span></p>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Bagikan kode ini untuk mengundang anggota baru</p>
            </div>
        </div>
    </div>

    <?php if ($is_team_owner): ?>
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Kelola Anggota Tim (<?php echo count($users_in_team); ?>)</h2>
        <div class="space-y-4 mb-6">
            <?php foreach ($users_in_team as $member): ?>
            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center">
                        <span class="text-white font-medium text-sm">
                            <?php echo strtoupper(substr($member['nama_lengkap'], 0, 1)); ?>
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($member['nama_lengkap']); ?></span>
                        <span class="text-gray-500 text-sm ml-2">(<?php echo ucfirst($member['role_tim']); ?>)</span>
                    </div>
                </div>
                <?php if ($member['id_user'] !== $current_user['id']): // Don't allow owner to remove self ?>
                <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus <?php echo htmlspecialchars($member['nama_lengkap']); ?> dari tim ini? Tugas yang ditugaskan kepadanya akan dihapus penugasannya.');">
                    <input type="hidden" name="member_id_to_remove" value="<?php echo htmlspecialchars($member['id_user']); ?>">
                    <button type="submit" name="remove_member" class="text-red-600 hover:text-red-800 transition-colors duration-200 text-sm font-medium">Hapus</button>
                </form>
                <?php else: ?>
                    <span class="text-gray-500 text-sm">Anda (Owner)</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <h3 class="text-lg font-semibold text-gray-900 mb-4">Tambahkan Anggota Baru</h3>
        <form method="POST" class="space-y-4">
            <div>
                <label for="member_email" class="block text-sm font-medium text-gray-700 mb-2">Email Anggota</label>
                <input type="email" id="member_email" name="member_email" required
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Masukkan email anggota...">
                <p class="mt-1 text-xs text-gray-500">Anggota harus sudah terdaftar di DoItLater.id.</p>
            </div>
            <div>
                <label for="member_role" class="block text-sm font-medium text-gray-700 mb-2">Peran Anggota</label>
                <select id="member_role" name="member_role" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="member">Anggota Biasa</option>
                    </select>
            </div>
            <button type="submit" name="add_member_by_email"
                    class="bg-gradient-to-r from-blue-500 to-green-500 text-white px-6 py-3 rounded-lg hover:from-blue-600 hover:to-green-600 transition-all duration-200 font-medium">
                Tambah Anggota
            </button>
        </form>
    </div>
    <?php endif; ?>

<div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
    <h2 class="text-xl font-semibold text-gray-900 mb-4">Tambah Tugas Baru</h2>
    <form method="POST" class="space-y-4">
        <!-- Grid Judul + Deadline -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Judul Tugas</label>
                <input type="text" name="judul_tugas" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Deadline</label>
                <input type="date" name="tanggal_deadline"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
        </div>

        <!-- Deskripsi -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
            <textarea name="deskripsi" rows="3"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- Kategori -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Kategori</label>
                <select id="kategoriSelect" name="id_kategori"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent mb-2">
                    <option value="">Pilih Kategori</option>
                    <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id_kategori']; ?>" data-nama="<?php echo htmlspecialchars($category['nama_kategori']); ?>">
                        <?php echo htmlspecialchars($category['nama_kategori']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <!-- Tombol Aksi -->
                <div class="flex gap-4 mb-2">
                    <button type="button" onclick="showEditForm()" class="flex items-center gap-1 text-blue-600 hover:text-blue-800 font-medium">
                        Edit
                    </button>
                    <button type="submit" name="delete_category" onclick="return confirm('Yakin ingin menghapus kategori ini?');"
                        class="flex items-center gap-1 text-red-600 hover:text-red-800 font-medium">
                        Hapus
                    </button>
                    <input type="hidden" name="delete_kategori_id" id="delete_kategori_id">
                </div>

                <!-- Form Edit -->
                <div id="editForm" class="hidden mb-2">
                    <input type="hidden" name="edit_kategori_id" id="edit_kategori_id">
                    <label class="block text-sm text-gray-700 mb-1">Nama Baru:</label>
                    <input type="text" name="new_nama_kategori" id="new_nama_kategori"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent mb-2">
                    <button type="submit" name="edit_category"
                        class="bg-gradient-to-r from-purple-500 to-blue-500 text-white px-6 py-2 rounded-lg hover:from-purple-600 hover:to-blue-600 font-medium">
                        Simpan Perubahan
                    </button>
                </div>
            </div>

            <!-- Penanggung Jawab -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Penanggung Jawab</label>
                <select name="id_penanggung_jawab"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Pilih Penanggung Jawab</option>
                    <?php foreach ($users_in_team as $user): ?>
                    <option value="<?php echo $user['id_user']; ?>">
                        <?php echo htmlspecialchars($user['nama_lengkap']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Tombol Submit -->
        <button type="submit" name="add_task"
                class="bg-gradient-to-r from-purple-500 to-blue-500 text-white px-6 py-3 rounded-lg hover:from-purple-600 hover:to-blue-600 transition-all duration-200 font-medium">
            Tambah Tugas
        </button>
    </form>
</div>


    <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Tambah Kategori Baru</h2>
        <form method="POST" class="flex gap-4">
            <input type="text" name="nama_kategori" required
                   class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                   placeholder="Nama kategori...">
            <button type="submit" name="add_category"
                    class="bg-gradient-to-r from-green-500 to-teal-500 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-teal-600 transition-all duration-200 font-medium">
                Tambah Kategori
            </button>
        </form>
    </div>

    <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Filter & Urutkan</h2>
        <div class="flex flex-wrap gap-4">
            <a href="team.php?id=<?php echo $team_id; ?>"
               class="px-4 py-2 rounded-lg <?php echo $filter_kategori == 0 ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-all duration-200">
                Semua Kategori
            </a>
            <?php foreach ($categories as $category): ?>
            <a href="team.php?id=<?php echo $team_id; ?>&kategori=<?php echo $category['id_kategori']; ?>&sort=<?php echo $sort_by; ?>"
               class="px-4 py-2 rounded-lg <?php echo $filter_kategori == $category['id_kategori'] ? 'bg-purple-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-all duration-200">
                <?php echo htmlspecialchars($category['nama_kategori']); ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 flex flex-wrap gap-4">
            <span class="text-gray-700 font-medium">Urutkan:</span>
            <a href="team.php?id=<?php echo $team_id; ?>&kategori=<?php echo $filter_kategori; ?>&sort=created"
               class="px-4 py-2 rounded-lg <?php echo $sort_by == 'created' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-all duration-200">
                Terbaru
            </a>
            <a href="team.php?id=<?php echo $team_id; ?>&kategori=<?php echo $filter_kategori; ?>&sort=deadline"
               class="px-4 py-2 rounded-lg <?php echo $sort_by == 'deadline' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-all duration-200">
                Deadline
            </a>
            <a href="team.php?id=<?php echo $team_id; ?>&kategori=<?php echo $filter_kategori; ?>&sort=priority"
               class="px-4 py-2 rounded-lg <?php echo $sort_by == 'priority' ? 'bg-blue-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?> transition-all duration-200">
                Prioritas
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                <span class="w-4 h-4 bg-orange-500 rounded-full mr-3"></span>
                Tugas Berjalan (<?php echo count($ongoing_tasks); ?>)
            </h2>

            <div class="space-y-4">
                <?php foreach ($ongoing_tasks as $task): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 <?php echo $task['is_overdue'] ? 'border-red-500' : 'border-orange-500'; ?>">
                    <div class="flex justify-between items-start mb-3">
                        <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($task['judul_tugas']); ?></h3>
                        <?php if ($task['is_overdue']): ?>
                        <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full">Terlambat</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($task['deskripsi']): ?>
                    <p class="text-gray-600 mb-3"><?php echo htmlspecialchars($task['deskripsi']); ?></p>
                    <?php endif; ?>

                    <div class="flex flex-wrap gap-2 mb-4">
                        <?php if ($task['nama_kategori']): ?>
                        <span class="bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full">
                            <?php echo htmlspecialchars($task['nama_kategori']); ?>
                        </span>
                        <?php endif; ?>

                        <?php if ($task['penanggung_jawab_nama']): ?>
                        <span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">
                            <?php echo htmlspecialchars($task['penanggung_jawab_nama']); ?>
                        </span>
                        <?php endif; ?>

                        <?php if ($task['tanggal_deadline']): ?>
                        <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full">
                            <?php echo date('d M Y', strtotime($task['tanggal_deadline'])); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <form method="POST" class="inline">
                        <input type="hidden" name="task_id" value="<?php echo $task['id_tugas']; ?>">
                        <button type="submit" name="complete_task"
                                class="bg-gradient-to-r from-green-500 to-teal-500  text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-teal-600 transition-all duration-200 text-sm font-medium">
                            ✓ Tandai Selesai
                        </button>
                    </form><!-- Tombol Edit -->
                        <button type="button"
                                onclick="toggleEditForm(<?php echo $task['id_tugas']; ?>)"
                                class="text-blue-500 hover:text-blue-700 text-sm font-medium mt-2">
                              Edit
                        </button>

                        <form method="POST" class="inline-block ml-2" onsubmit="return confirm('Yakin ingin menghapus tugas ini?');">
                            <input type="hidden" name="delete_task_id" value="<?php echo $task['id_tugas']; ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                Hapus
                            </button>
                        </form>

                        <!-- Edit Form (hidden by default) -->
                        <form method="POST" id="edit-form-<?php echo $task['id_tugas']; ?>" class="mt-4 hidden space-y-3">
                            <input type="hidden" name="edit_task_id" value="<?php echo $task['id_tugas']; ?>">

                            <input type="text" name="edit_judul_tugas"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                                value="<?php echo htmlspecialchars($task['judul_tugas']); ?>" required>

                            <textarea name="edit_deskripsi"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg"><?php echo htmlspecialchars($task['deskripsi']); ?></textarea>

                            <input type="date" name="edit_tanggal_deadline"
                                value="<?php echo htmlspecialchars($task['tanggal_deadline']); ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg">

                            <select name="edit_id_kategori"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id_kategori']; ?>"
                                        <?php echo $task['id_kategori'] == $category['id_kategori'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['nama_kategori']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <select name="edit_id_penanggung_jawab"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="">Pilih Penanggung Jawab</option>
                                <?php foreach ($users_in_team as $user): ?>
                                    <option value="<?php echo $user['id_user']; ?>"
                                        <?php echo $task['id_penanggung_jawab'] == $user['id_user'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['nama_lengkap']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <button type="submit" name="update_task"
                                class="bg-gradient-to-r from-green-500 to-teal-500  text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-teal-600 transition-all duration-200 text-sm font-medium">
                                Simpan Perubahan
                            </button>
                        </form>
                </div>
                <?php endforeach; ?>

                <?php if (empty($ongoing_tasks)): ?>
                <div class="text-center py-12">
                    <div class="text-gray-400 text-4xl mb-4">📝</div>
                    <p class="text-gray-500">Belum ada tugas yang sedang berjalan</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                <span class="w-4 h-4 bg-green-500 rounded-full mr-3"></span>
                Tugas Selesai (<?php echo count($done_tasks); ?>)
            </h2>

            <div class="space-y-4">
                <?php foreach ($done_tasks as $task): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 opacity-75">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                        <?php echo htmlspecialchars($task['judul_tugas']); ?>
                    </h3>

                    <div class="flex flex-wrap gap-2 mb-3">
                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                            Diselesaikan oleh: <?php echo htmlspecialchars($task['penyelesai_nama']); ?>
                        </span>
                        <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full">
                            <?php echo date('d M Y H:i', strtotime($task['tanggal_selesai'])); ?>
                        </span>
                    </div>
                    <!-- Tombol Aksi -->
                    <div class="flex items-center gap-3 mb-3">
                        <!-- Undo -->
                        <form method="POST" class="inline-block ml-2" onsubmit="return confirm('Yakin ingin menghapus tugas ini?');">
                            <input type="hidden" name="delete_task_id" value="<?php echo $task['id_tugas_asli']; ?>">
                            <input type="hidden" name="source" value="done"> 
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">
                                Hapus
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if (empty($done_tasks)): ?>
                <div class="text-center py-12">
                    <div class="text-gray-400 text-4xl mb-4">✅</div>
                    <p class="text-gray-500">Belum ada tugas yang selesai</p>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<div id="editTeamNameModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">Edit Nama Tim</h3>
        <form method="POST">
            <input type="hidden" name="id_team" value="<?php echo htmlspecialchars($team_id); ?>">
            <div class="mb-4">
                <label for="new_nama_team" class="block text-sm font-medium text-gray-700">Nama Tim Baru</label>
                <input type="text" id="new_nama_team" name="new_nama_team" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-purple-500 focus:border-transparent sm:text-sm"
                       value="<?php echo htmlspecialchars($team['nama_team']); ?>">
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeEditTeamNameModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">Batal</button>
                <button type="submit" name="update_team_name" class="px-4 py-2 bg-gradient-to-r from-purple-500 to-blue-500 text-white rounded-md hover:from-purple-600 hover:to-blue-600">Perbarui</button>
            </div>
        </form>
    </div>
</div>

<script>
    // JavaScript for Edit Team Name Modal
    function openEditTeamNameModal() {
        document.getElementById('editTeamNameModal').classList.remove('hidden');
    }

    function closeEditTeamNameModal() {
        document.getElementById('editTeamNameModal').classList.add('hidden');
    }
</script>

<script>
function showEditForm() {
    const select = document.getElementById('kategoriSelect');
    const selectedId = select.value;
    const selectedName = select.options[select.selectedIndex].dataset.nama;

    if (!selectedId) {
        alert('Pilih kategori terlebih dahulu.');
        return;
    }

    document.getElementById('edit_kategori_id').value = selectedId;
    document.getElementById('new_nama_kategori').value = selectedName;
    document.getElementById('editForm').classList.remove('hidden');
}

document.getElementById('kategoriSelect').addEventListener('change', function () {
    const selectedId = this.value;
    document.getElementById('delete_kategori_id').value = selectedId;
    document.getElementById('editForm').classList.add('hidden'); // hide edit form when changing
});
</script>

<script>
function toggleEditForm(taskId) {
    const form = document.getElementById('edit-form-' + taskId);
    form.classList.toggle('hidden');
}
</script>

<?php include 'includes/footer.php'; ?>
