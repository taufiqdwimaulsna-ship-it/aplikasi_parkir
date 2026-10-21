<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'petugas') {
    header('Location: ../index.php');
    exit;
}

$id_user = $_SESSION['user']['id_user'];
$notif = '';
$status_type = '';

// Proses Transaksi Masuk
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'masuk') {
    $plat_nomor      = strtoupper(trim($_POST['plat_nomor']));
    $jenis_kendaraan = $_POST['jenis_kendaraan'];
    $warna           = trim($_POST['warna']);
    $pemilik         = trim($_POST['pemilik']);
    $id_area         = (int)$_POST['id_area'];

    try {
        $pdo->beginTransaction();

        // 1. Kunci dan cek kuota area parkir (Pessimistic Locking)
        $stmtArea = $pdo->prepare("SELECT kapasitas, terisi, nama_area FROM tb_area_parkir WHERE id_area = :id_area FOR UPDATE");
        $stmtArea->execute([':id_area' => $id_area]);
        $area = $stmtArea->fetch();

        if (!$area || $area['terisi'] >= $area['kapasitas']) {
            throw new Exception("Area parkir {$area['nama_area']} sudah penuh!");
        }

        // 2. Simpan atau ambil id_kendaraan
        $stmtCek = $pdo->prepare("SELECT id_kendaraan FROM tb_kendaraan WHERE plat_nomor = :plat LIMIT 1");
        $stmtCek->execute([':plat' => $plat_nomor]);
        $kendaraan = $stmtCek->fetch();

        if ($kendaraan) {
            $id_kendaraan = $kendaraan['id_kendaraan'];
        } else {
            $insK = $pdo->prepare("INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user) VALUES (?, ?, ?, ?, ?)");
            $insK->execute([$plat_nomor, $jenis_kendaraan, $warna, $pemilik, $id_user]);
            $id_kendaraan = $pdo->lastInsertId();
        }

        // 3. Ambil id_tarif sesuai tipe kendaraan
        $stmtTarif = $pdo->prepare("SELECT id_tarif FROM tb_tarif WHERE jenis_kendaraan = :jenis LIMIT 1");
        $stmtTarif->execute([':jenis' => $jenis_kendaraan]);
        $tarif = $stmtTarif->fetch();
        $id_tarif = $tarif ? $tarif['id_tarif'] : 1;

        // 4. Catat transaksi masuk
        $insT = $pdo->prepare("INSERT INTO tb_transaksi (id_kendaraan, waktu_masuk, id_tarif, status, id_user, id_area) VALUES (?, NOW(), ?, 'masuk', ?, ?)");
        $insT->execute([$id_kendaraan, $id_tarif, $id_user, $id_area]);
        $id_parkir_baru = $pdo->lastInsertId();

        // 5. Update kuota area parkir
        $updArea = $pdo->prepare("UPDATE tb_area_parkir SET terisi = terisi + 1 WHERE id_area = ?");
        $updArea->execute([$id_area]);

        // 6. Catat log
        $insLog = $pdo->prepare("INSERT INTO tb_log_aktivitas (id_user, aktivitas) VALUES (?, ?)");
        $insLog->execute([$id_user, "Kendaraan masuk plat: {$plat_nomor} (Tiket #{$id_parkir_baru})"]);

        $pdo->commit();
        $notif = "Tiket #{$id_parkir_baru} berhasil dicetak! Kendaraan {$plat_nomor} masuk.";
        $status_type = 'success';
    } catch (Exception $e) {
        $pdo->rollBack();
        $notif = "Gagal memproses transaksi: " . $e->getMessage();
        $status_type = 'danger';
    }
}

// Ambil data untuk form
$areas  = $pdo->query("SELECT * FROM tb_area_parkir")->fetchAll();
$tarifs = $pdo->query("SELECT * FROM tb_tarif")->fetchAll();

// Daftar kendaraan yang sedang parkir (status = 'masuk')
$aktif = $pdo->query("
    SELECT t.id_parkir, t.waktu_masuk, k.plat_nomor, k.jenis_kendaraan, k.warna, a.nama_area 
    FROM tb_transaksi t
    JOIN tb_kendaraan k ON k.id_kendaraan = t.id_kendaraan
    JOIN tb_area_parkir a ON a.id_area = t.id_area
    WHERE t.status = 'masuk'
    ORDER BY t.waktu_masuk DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Petugas Parkir - Transaksi</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen">
    <header class="bg-slate-800 border-b border-slate-700 px-6 py-4 flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold text-emerald-400">Loket Petugas Parkir</h1>
            <p class="text-xs text-slate-400">Petugas Bertugas: <?= htmlspecialchars($_SESSION['user']['nama_lengkap']) ?></p>
        </div>
        <a href="../logout.php" class="px-4 py-2 bg-rose-500/20 hover:bg-rose-500/30 text-rose-400 text-xs font-semibold rounded-lg border border-rose-500/30 transition">
            Logout
        </a>
    </header>

    <main class="max-w-6xl mx-auto p-6 space-y-6">
        <?php if (!empty($notif)): ?>
            <div class="p-4 rounded-xl border text-sm <?= $status_type === 'success' ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' : 'bg-rose-500/10 border-rose-500/20 text-rose-400' ?>">
                <?= htmlspecialchars($notif) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form Masuk -->
            <div class="bg-slate-800 border border-slate-700 rounded-xl p-5">
                <h2 class="text-base font-semibold text-slate-200 mb-4">Input Kendaraan Masuk</h2>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="masuk">

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Plat Nomor</label>
                        <input type="text" name="plat_nomor" required placeholder="B 1234 XYZ" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none focus:border-emerald-500 uppercase">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Jenis Kendaraan</label>
                        <select name="jenis_kendaraan" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none">
                            <option value="motor">Motor</option>
                            <option value="mobil">Mobil</option>
                            <option value="lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Warna / Keterangan</label>
                        <input type="text" name="warna" placeholder="Hitam" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Nama Pemilik (Opsional)</label>
                        <input type="text" name="pemilik" placeholder="Pengendara" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-400 mb-1">Area Parkir</label>
                        <select name="id_area" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-slate-100 focus:outline-none">
                            <?php foreach ($areas as $a): ?>
                                <option value="<?= $a['id_area'] ?>" <?= ($a['terisi'] >= $a['kapasitas']) ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($a['nama_area']) ?> (Sisa: <?= $a['kapasitas'] - $a['terisi'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="w-full mt-2 py-2.5 bg-emerald-500 hover:bg-emerald-600 font-semibold rounded-lg text-slate-950 text-sm transition">
                        Cetak Tiket Masuk
                    </button>
                </form>
            </div>

            <!-- Tabel Kendaraan Aktif -->
            <div class="lg:col-span-2 bg-slate-800 border border-slate-700 rounded-xl p-5">
                <h2 class="text-base font-semibold text-slate-200 mb-4">Kendaraan Sedang Terparkir (<?= count($aktif) ?>)</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-900 text-slate-400 uppercase">
                            <tr>
                                <th class="p-2.5">ID Tiket</th>
                                <th class="p-2.5">Plat Nomor</th>
                                <th class="p-2.5">Jenis</th>
                                <th class="p-2.5">Area</th>
                                <th class="p-2.5">Waktu Masuk</th>
                                <th class="p-2.5 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700">
                            <?php if (empty($aktif)): ?>
                                <tr>
                                    <td colspan="6" class="p-4 text-center text-slate-500">Tidak ada kendaraan aktif saat ini.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($aktif as $row): ?>
                                <tr class="hover:bg-slate-700/30">
                                    <td class="p-2.5 font-mono text-emerald-400 font-bold">#<?= $row['id_parkir'] ?></td>
                                    <td class="p-2.5 font-bold text-slate-100"><?= htmlspecialchars($row['plat_nomor']) ?></td>
                                    <td class="p-2.5 capitalize"><?= htmlspecialchars($row['jenis_kendaraan']) ?></td>
                                    <td class="p-2.5"><?= htmlspecialchars($row['nama_area']) ?></td>
                                    <td class="p-2.5 text-slate-400"><?= $row['waktu_masuk'] ?></td>
                                    <td class="p-2.5 text-center">
                                        <a href="proses_keluar.php?id=<?= $row['id_parkir'] ?>" onclick="return confirm('Proses checkout kendaraan ini?')" class="px-3 py-1 bg-amber-500/20 hover:bg-amber-500/30 text-amber-400 font-semibold rounded border border-amber-500/30">
                                            Checkout
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>