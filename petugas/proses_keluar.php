<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'petugas') {
    header('Location: ../index.php');
    exit;
}

$id_parkir = (int)($_GET['id'] ?? 0);
$id_user   = $_SESSION['user']['id_user'];
$result    = null;
$error     = '';

if ($id_parkir > 0) {
    try {
        // Eksekusi Stored Procedure yang sudah dibuat pada Kegiatan 4
        $stmt = $pdo->prepare("CALL sp_parkir_keluar(?, ?)");
        $stmt->execute([$id_parkir, $id_user]);
        $result = $stmt->fetch();
        $stmt->closeCursor();

        // Ambil detail transaksi untuk struk
        $stmtDetail = $pdo->prepare("
            SELECT t.*, k.plat_nomor, k.jenis_kendaraan, a.nama_area 
            FROM tb_transaksi t
            JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
            JOIN tb_area_parkir a ON a.id_area = t.id_area
            WHERE t.id_parkir = ?
        ");
        $stmtDetail->execute([$id_parkir]);
        $detail = $stmtDetail->fetch();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} else {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembayaran Parkir #<?= $id_parkir ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-slate-800 border border-slate-700 w-full max-w-sm rounded-2xl p-6 shadow-2xl">
        <?php if (!empty($error)): ?>
            <div class="p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs rounded-lg text-center mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
            <a href="index.php" class="block text-center text-xs text-slate-400 hover:underline">Kembali ke panel</a>
        <?php else: ?>
            <div class="text-center pb-4 border-b border-slate-700 border-dashed">
                <h1 class="font-bold text-lg text-emerald-400">STRUK PARKIR</h1>
                <p class="text-xs text-slate-400 font-mono">Tiket #<?= $detail['id_parkir'] ?></p>
            </div>

            <div class="py-4 space-y-2 text-xs">
                <div class="flex justify-between"><span class="text-slate-400">Plat Nomor:</span> <span class="font-bold text-slate-200"><?= htmlspecialchars($detail['plat_nomor']) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-400">Jenis:</span> <span class="capitalize text-slate-200"><?= htmlspecialchars($detail['jenis_kendaraan']) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-400">Area:</span> <span class="text-slate-200"><?= htmlspecialchars($detail['nama_area']) ?></span></div>
                <div class="flex justify-between"><span class="text-slate-400">Waktu Masuk:</span> <span class="text-slate-200"><?= $detail['waktu_masuk'] ?></span></div>
                <div class="flex justify-between"><span class="text-slate-400">Waktu Keluar:</span> <span class="text-slate-200"><?= $detail['waktu_keluar'] ?></span></div>
                <div class="flex justify-between"><span class="text-slate-400">Durasi:</span> <span class="text-slate-200"><?= $detail['durasi_jam'] ?> Jam</span></div>
                
                <div class="pt-3 border-t border-slate-700 flex justify-between items-center text-sm">
                    <span class="font-bold text-slate-300">Total Biaya:</span>
                    <span class="font-bold text-emerald-400 font-mono text-base">Rp <?= number_format($detail['biaya_total'], 0, ',', '.') ?></span>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-700 border-dashed flex gap-2">
                <button onclick="window.print()" class="flex-1 py-2 bg-slate-700 hover:bg-slate-600 text-xs font-semibold rounded-lg transition">
                    Cetak Struk
                </button>
                <a href="index.php" class="flex-1 py-2 bg-emerald-500 hover:bg-emerald-600 text-slate-950 text-center text-xs font-semibold rounded-lg transition">
                    Selesai
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>