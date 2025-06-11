<?php
$page_title = 'Bergabung dengan Tim';
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$current_user = getCurrentUser();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $team_code = trim(strtoupper($_POST['team_code']));
    
    if (empty($team_code)) {
        $error = 'Kode tim harus diisi';
    } else {
        // Check if team exists
        $query = "SELECT id_team, nama_team FROM tbl_team WHERE team_code = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$team_code]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$team) {
            $error = 'Kode tim tidak ditemukan';
        } else {
            // Check if user is already a member
            $query = "SELECT id_member FROM tbl_team_members WHERE id_team = ? AND id_user = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$team['id_team'], $current_user['id']]);
            
            if ($stmt->fetch()) {
                $error = 'Anda sudah menjadi anggota tim ini';
            } else {
                // Add user to team
                $query = "INSERT INTO tbl_team_members (id_team, id_user, role_tim) VALUES (?, ?, 'member')";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$team['id_team'], $current_user['id']])) {
                    $success = 'Berhasil bergabung dengan tim: ' . htmlspecialchars($team['nama_team']);
                } else {
                    $error = 'Terjadi kesalahan saat bergabung dengan tim';
                }
            }
        }
    }
}

include 'includes/header.php';
include 'includes/navbar.php';
?>

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-white rounded-2xl shadow-lg p-8">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-gradient-to-r from-green-500 to-teal-500 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <span class="text-white font-bold text-2xl">+</span>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Bergabung dengan Tim</h1>
            <p class="text-gray-600">Masukkan kode tim untuk bergabung dengan tim yang sudah ada</p>
        </div>
        
        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-red-600 text-sm"><?php echo htmlspecialchars($error); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-green-600 text-sm"><?php echo htmlspecialchars($success); ?></p>
            <div class="mt-4">
                <a href="dashboard.php" class="text-green-700 hover:text-green-800 font-medium">
                    ← Kembali ke Dashboard
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="space-y-6">
            <div>
                <label for="team_code" class="block text-sm font-medium text-gray-700 mb-2">
                    Kode Tim
                </label>
                <input type="text" id="team_code" name="team_code" required 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all duration-200 text-center text-lg font-mono"
                       placeholder="Contoh: TEAM0001"
                       style="text-transform: uppercase;">
            </div>
            
            <button type="submit" 
                    class="w-full bg-gradient-to-r from-green-500 to-teal-500 text-white py-3 px-4 rounded-lg hover:from-green-600 hover:to-teal-600 transition-all duration-200 font-medium">
                Bergabung dengan Tim
            </button>
        </form>
        
        <div class="mt-8 p-4 bg-blue-50 rounded-lg">
            <h3 class="font-semibold text-blue-900 mb-2">💡 Tips:</h3>
            <ul class="text-blue-800 text-sm space-y-1">
                <li>• Minta kode tim dari pemilik atau anggota tim lainnya</li>
                <li>• Kode tim biasanya berformat TEAM0001, TEAM0002, dst.</li>
                <li>• Setelah bergabung, Anda bisa ditugaskan dalam tugas-tugas tim</li>
            </ul>
        </div>
        
        <div class="mt-6 text-center">
            <a href="dashboard.php" class="text-gray-600 hover:text-gray-700">
                ← Kembali ke Dashboard
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
