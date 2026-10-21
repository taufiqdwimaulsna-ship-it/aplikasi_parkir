<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Ambil data area
$areas = $pdo->query("SELECT * FROM tb_area_parkir")->fetchAll();

// Ambil data tarif
$tarifs = $pdo->query("SELECT * FROM tb_tarif")->fetchAll();

// Ambil 10 log aktivitas terbaru
$logs = $pdo->query("
    SELECT l.*, u.nama_lengkap, u.role 
    FROM tb_log_aktivitas l 
    JOIN tb_user u ON u.id_user = l.id_user 
    ORDER BY l.waktu_aktivitas DESC 
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Aplikasi Parkir</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
    <!-- Navbar -->
    <header class="bg-slate-800 border-b border-slate-700 px-6 py-4 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-emerald-400">Panel Administrator</h1>
            <p class="text-xs text-slate-400">Selamat datang, <?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?></p>
        </div>
        <a href="../logout.php" class="px-4 py-2 bg-rose-500/20 hover:bg-rose-500/30 text-rose-400 text-xs font-semibold rounded-lg border border-rose-500/30 transition">
            Keluar (Logout)
        </a>
    </header>

    <main class="max-w-6xl mx-auto p-6 space-y-8">
        <!-- Area Parkir -->
        <section>
            <h2 class="text-lg font-semibold text-slate-200 mb-3">Status Daya Tampung Area Parkir</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($areas as $area): ?>
                    <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-slate-200"><?= htmlspecialchars($area['nama_area']) ?></span>
                            <span class="text-xs px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                Sisa Slot: <?= $area['kapasitas'] - $area['terisi'] ?>
                            </span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-3 mt-3 overflow-hidden">
                            <?php $persen = ($area['kapasitas'] > 0) ? ($area['terisi'] / $area['kapasitas']) * 100 : 0; ?>
                            <div class="bg-emerald-500 h-3 rounded-full transition-all" style="width: <?= min(100, $persen) ?>%"></div>
                        </div>
                        <p class="text-xs text-slate-400 mt-2">Terisi: <?= $area['terisi'] ?> / Kapasitas: <?= $area['kapasitas'] ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Tarif & Log -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Tarif -->
            <section class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                <h2 class="text-base font-semibold text-slate-200 mb-3">Daftar Tarif Parkir</h2>
                <div class="space-y-3">
                    <?php foreach ($tarifs as $tarif): ?>
                        <div class="flex justify-between items-center p-3 bg-slate-900 rounded-lg border border-slate-700/50">
                            <span class="capitalize text-slate-300"><?= htmlspecialchars($tarif['jenis_kendaraan']) ?></span>
                            <span class="font-mono text-emerald-400 font-bold">Rp <?= number_format($tarif['tarif_per_jam'], 0, ',', '.') ?>/jam</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <!-- Log Aktivitas -->
            <section class="lg:col-span-2 bg-slate-800 border border-slate-700 rounded-xl p-5">
                <h2 class="text-base font-semibold text-slate-200 mb-3">Log Aktivitas Pengguna (Audit Trail)</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900 text-slate-400 uppercase">
                            <tr>
                                <th class="p-2.5">Waktu</th>
                                <th class="p-2.5">User</th>
                                <th class="p-2.5">Aktivitas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700">
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-slate-700/30">
                                    <td class="p-2.5 text-slate-400 whitespace-nowrap"><?= $log['waktu_aktivitas'] ?></td>
                                    <td class="p-2.5 font-semibold text-slate-200"><?= htmlspecialchars($log['nama_lengkap']) ?> (<?= $log['role'] ?>)</td>
                                    <td class="p-2.5 text-slate-300"><?= htmlspecialchars($log['aktivitas']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</body>
</html>