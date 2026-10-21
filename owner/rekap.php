<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'owner') {
    header('Location: ../index.php');
    exit;
}

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date   = $_GET['end_date'] ?? date('Y-m-d');

// Membaca data rekap dari VIEW v_rekap_transaksi sesuai Kegiatan 3
$stmt = $pdo->prepare("
    SELECT * FROM v_rekap_transaksi 
    WHERE DATE(waktu_masuk) BETWEEN :start_date AND :end_date
    ORDER BY waktu_masuk DESC
");
$stmt->execute([
    ':start_date' => $start_date,
    ':end_date'   => $end_date
]);
$rekaps = $stmt->fetchAll();

// Hitung total omzet pendapatan
$total_omzet = 0;
foreach ($rekaps as $r) {
    $total_omzet += (float)($r['biaya_total'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner - Rekap Transaksi Parkir</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
    <header class="bg-slate-800 border-b border-slate-700 px-6 py-4 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-emerald-400">Laporan Eksekutif Parkir</h1>
            <p class="text-xs text-slate-400">Pengguna: <?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?> (Owner)</p>
        </div>
        <a href="../logout.php" class="px-4 py-2 bg-rose-500/20 hover:bg-rose-500/30 text-rose-400 text-xs font-semibold rounded-lg border border-rose-500/30 transition">
            Logout
        </a>
    </header>

    <main class="max-w-6xl mx-auto p-6 space-y-6">
        <!-- Filter Form & Omzet Card -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2 bg-slate-800 border border-slate-700 rounded-xl p-5">
                <form method="GET" class="flex flex-wrap items-end gap-3 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-400 mb-1">Mulai Tanggal</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200">
                    </div>
                    <div>
                        <label class="block font-semibold text-slate-400 mb-1">Sampai Tanggal</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" class="px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-slate-200">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-semibold rounded-lg transition">
                        Terapkan Filter
                    </button>
                </form>
            </div>

            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5 flex flex-col justify-center">
                <span class="text-xs text-slate-400">Total Omzet Pendapatan</span>
                <span class="text-2xl font-bold text-emerald-400 font-mono mt-1">Rp <?= number_format($total_omzet, 0, ',', '.') ?></span>
            </div>
        </div>

        <!-- Tabel Rekap View -->
        <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-base font-semibold text-slate-200">Data View (v_rekap_transaksi)</h2>
                <span class="text-xs text-slate-400">Total Transaksi: <?= count($rekaps) ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-900 text-slate-400 uppercase">
                        <tr>
                            <th class="p-2.5">ID Parkir</th>
                            <th class="p-2.5">Plat Nomor</th>
                            <th class="p-2.5">Jenis</th>
                            <th class="p-2.5">Area</th>
                            <th class="p-2.5">Waktu Masuk</th>
                            <th class="p-2.5">Waktu Keluar</th>
                            <th class="p-2.5">Durasi</th>
                            <th class="p-2.5">Total Biaya</th>
                            <th class="p-2.5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php if (empty($rekaps)): ?>
                            <tr>
                                <td colspan="9" class="p-4 text-center text-slate-500">Tidak ada transaksi pada rentang tanggal ini.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($rekaps as $row): ?>
                            <tr class="hover:bg-slate-700/30">
                                <td class="p-2.5 font-mono text-emerald-400 font-bold">#<?= $row['id_parkir'] ?></td>
                                <td class="p-2.5 font-bold text-slate-100"><?= htmlspecialchars($row['plat_nomor']) ?></td>
                                <td class="p-2.5 capitalize"><?= htmlspecialchars($row['jenis_kendaraan']) ?></td>
                                <td class="p-2.5"><?= htmlspecialchars($row['nama_area']) ?></td>
                                <td class="p-2.5 text-slate-400"><?= $row['waktu_masuk'] ?></td>
                                <td class="p-2.5 text-slate-400"><?= $row['waktu_keluar'] ?? '-' ?></td>
                                <td class="p-2.5"><?= $row['durasi_jam'] ? $row['durasi_jam'] . ' jam' : '-' ?></td>
                                <td class="p-2.5 font-mono text-slate-200"><?= $row['biaya_total'] ? 'Rp ' . number_format($row['biaya_total'], 0, ',', '.') : '-' ?></td>
                                <td class="p-2.5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $row['status'] === 'masuk' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' ?>">
                                        <?= strtoupper($row['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>