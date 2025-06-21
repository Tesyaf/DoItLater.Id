<?php
$page_title = 'Dashboard';
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_team'])) {
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

        } catch (Exception $e) {
            $db->rollBack();
        }

        header('Location: dashboard.php');
        exit();
    }
}

$query = "SELECT DISTINCT t.*, u.nama_lengkap as pembuat_nama,
                (SELECT COUNT(*) FROM tbl_ongoing WHERE id_team = t.id_team) as total_ongoing,
                (SELECT COUNT(*) FROM tbl_done WHERE id_team = t.id_team) as total_done,
                tm.role_tim,
                t.team_code
                FROM tbl_team t 
                LEFT JOIN tbl_user u ON t.id_pembuat = u.id_user
                INNER JOIN tbl_team_members tm ON t.id_team = tm.id_team
                WHERE tm.id_user = ?
                ORDER BY t.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$current_user['id']]);
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Dashboard</h1>
        <p class="text-gray-600">Kelola tim dan tugas Anda dengan mudah</p>
    </div>
    
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Buat Tim Baru</h2>
        <form method="POST" class="flex gap-4">
            <input type="text" name="nama_team" required 
                   class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all duration-200"
                   placeholder="Masukkan nama tim...">
            <button type="submit" name="create_team" 
                    class="bg-gradient-to-r from-purple-500 to-blue-500 text-white px-6 py-3 rounded-lg hover:from-purple-600 hover:to-blue-600 transition-all duration-200 font-medium">
                Buat Tim
            </button>
        </form>
    </div>

    <div class="mb-8 text-center">
        <a href="join-team.php" 
           class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-green-500 to-teal-500 text-white rounded-lg hover:from-green-600 hover:to-teal-600 transition-all duration-200 font-medium">
            <span class="mr-2">+</span>
            Bergabung dengan Tim
        </a>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($teams as $team): ?>
        <div class="bg-white rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 overflow-hidden">
            <div class="bg-gradient-to-r from-purple-500 to-blue-500 p-6">
                <h3 class="text-xl font-semibold text-white mb-2"><?php echo htmlspecialchars($team['nama_team']); ?></h3>
                <p class="text-purple-100 text-sm">Dibuat oleh: <?php echo htmlspecialchars($team['pembuat_nama']); ?></p>
                <p class="text-purple-100 text-xs">
                    Kode: <?php echo htmlspecialchars($team['team_code'] ?? 'N/A'); ?> | 
                    Role: <?php echo ucfirst($team['role_tim']); ?>
                </p>
            </div>
            
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-500"><?php echo $team['total_ongoing']; ?></div>
                        <div class="text-sm text-gray-600">Ongoing</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-500"><?php echo $team['total_done']; ?></div>
                        <div class="text-sm text-gray-600">Done</div>
                    </div>
                </div>
                
                <a href="team.php?id=<?php echo $team['id_team']; ?>" 
                   class="block w-full bg-gradient-to-r from-gray-100 to-gray-200 text-gray-800 text-center py-3 rounded-lg hover:from-gray-200 hover:to-gray-300 transition-all duration-200 font-medium">
                    Lihat Tim
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if (empty($teams)): ?>
        <div class="col-span-full text-center py-12">
            <div class="text-gray-400 text-6xl mb-4">📋</div>
            <h3 class="text-xl font-semibold text-gray-600 mb-2">Belum ada tim</h3>
            <p class="text-gray-500">Buat tim pertama Anda untuk mulai mengelola tugas</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
