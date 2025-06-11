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

// Check if user is member of this team
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_task'])) {
        $judul = trim($_POST['judul_tugas']);
        $deskripsi = trim($_POST['deskripsi']);
        $kategori = !empty($_POST['id_kategori']) ? (int)$_POST['id_kategori'] : null;
        $penanggung_jawab = !empty($_POST['id_penanggung_jawab']) ? (int)$_POST['id_penanggung_jawab'] : null;
        $deadline = $_POST['tanggal_deadline'];
        
        if (!empty($judul)) {
            $query = "INSERT INTO tbl_ongoing (id_team, id_kategori, id_penanggung_jawab, judul_tugas, deskripsi, tanggal_deadline) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$team_id, $kategori, $penanggung_jawab, $judul, $deskripsi, $deadline]);
        }
    } elseif (isset($_POST['complete_task'])) {
        $task_id = (int)$_POST['task_id'];
        
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
        }
    } elseif (isset($_POST['add_category'])) {
        $nama_kategori = trim($_POST['nama_kategori']);
        
        if (!empty($nama_kategori)) {
            $query = "INSERT INTO tbl_kategori (id_team, nama_kategori) VALUES (?, ?)";
            $stmt = $db->prepare($query);
            $stmt->execute([$team_id, $nama_kategori]);
        }
    }
    
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

// Get team members for assignment
$query = "SELECT u.id_user, u.nama_lengkap 
          FROM tbl_user u
          INNER JOIN tbl_team_members tm ON u.id_user = tm.id_user
          WHERE tm.id_team = ?
          ORDER BY u.nama_lengkap";
$stmt = $db->prepare($query);
$stmt->execute([$team_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        $order_clause .= "CASE WHEN o.tanggal_deadline < CURDATE() THEN 1 ELSE 2 END, o.tanggal_deadline ASC";
        break;
    default:
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
    <!-- Team Header -->
    <div class="bg-gradient-to-r from-purple-500 to-blue-500 rounded-2xl p-8 mb-8 text-white">
        <h1 class="text-3xl font-bold mb-2"><?php echo htmlspecialchars($team['nama_team']); ?></h1>
        <p class="text-purple-100">Dibuat oleh: <?php echo htmlspecialchars($team['pembuat_nama']); ?></p>
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

    <!-- Team Info -->
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

    <!-- Team Members -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Anggota Tim (<?php echo count($users); ?>)</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($users as $user): ?>
            <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center">
                    <span class="text-white font-medium text-sm">
                        <?php echo strtoupper(substr($user['nama_lengkap'], 0, 1)); ?>
                    </span>
                </div>
                <span class="text-gray-900 font-medium"><?php echo htmlspecialchars($user['nama_lengkap']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Add Task Form -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Tambah Tugas Baru</h2>
        <form method="POST" class="space-y-4">
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
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Deskripsi</label>
                <textarea name="deskripsi" rows="3" 
                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                    <select name="id_kategori" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="">Pilih Kategori</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id_kategori']; ?>">
                            <?php echo htmlspecialchars($category['nama_kategori']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Penanggung Jawab</label>
                    <select name="id_penanggung_jawab" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="">Pilih Penanggung Jawab</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id_user']; ?>">
                            <?php echo htmlspecialchars($user['nama_lengkap']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <button type="submit" name="add_task" 
                    class="bg-gradient-to-r from-purple-500 to-blue-500 text-white px-6 py-3 rounded-lg hover:from-purple-600 hover:to-blue-600 transition-all duration-200 font-medium">
                Tambah Tugas
            </button>
        </form>
    </div>
    
    <!-- Add Category Form -->
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
    
    <!-- Filters -->
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
    
    <!-- Tasks Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Ongoing Tasks -->
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
                                class="bg-gradient-to-r from-green-500 to-teal-500 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-teal-600 transition-all duration-200 text-sm font-medium">
                            ✓ Tandai Selesai
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
        
        <!-- Done Tasks -->
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center">
                <span class="w-4 h-4 bg-green-500 rounded-full mr-3"></span>
                Tugas Selesai (<?php echo count($done_tasks); ?>)
            </h2>
            
            <div class="space-y-4">
                <?php foreach ($done_tasks as $task): ?>
                <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500 opacity-75">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($task['judul_tugas']); ?></h3>
                    
                    <div class="flex flex-wrap gap-2 mb-3">
                        <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">
                            Diselesaikan oleh: <?php echo htmlspecialchars($task['penyelesai_nama']); ?>
                        </span>
                        <span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded-full">
                            <?php echo date('d M Y H:i', strtotime($task['tanggal_selesai'])); ?>
                        </span>
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

<?php include 'includes/footer.php'; ?>
