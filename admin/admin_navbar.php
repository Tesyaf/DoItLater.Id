<?php
require_once '../config/session.php';
$current_user = getCurrentUser();
?>

<nav class="bg-white/80 backdrop-blur-sm border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex items-center">
                <a href="dashboard.php" class="flex items-center space-x-2">
                    <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-500 rounded-lg flex items-center justify-center">
                        <span class="text-white font-bold text-sm">Do</span>
                    </div>
                    <span class="text-xl font-bold bg-gradient-to-r from-purple-600 to-blue-600 bg-clip-text text-transparent">
                        DoItLater.id
                    </span>
                </a>
            </div>
            
            <?php if ($current_user): ?>
            <div class="flex items-center space-x-4">
                <span class="text-gray-700">Halo, <?php echo htmlspecialchars($current_user['nama']); ?></span>
                <a href="../logout.php" class="bg-gradient-to-r from-red-500 to-pink-500 text-white px-4 py-2 rounded-lg hover:from-red-600 hover:to-pink-600 transition-all duration-200 font-medium">
                    Logout
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
