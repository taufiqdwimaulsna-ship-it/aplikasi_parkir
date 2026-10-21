<?php
session_start();
require_once 'config/database.php';

// Jika sudah login, redirect sesuai role
if (isset($_SESSION['user'])) {
    header("Location: " . $_SESSION['user']['role'] . ($_SESSION['user']['role'] === 'admin' ? '/dashboard.php' : ($_SESSION['user']['role'] === 'petugas' ? '/index.php' : '/rekap.php')));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        $hashPassword = hash('sha256', $password);

        $stmt = $pdo->prepare("SELECT * FROM tb_user WHERE username = :username AND password = :password AND status_aktif = 1 LIMIT 1");
        $stmt->execute([
            ':username' => $username,
            ':password' => $hashPassword
        ]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['user'] = [
                'id_user'      => $user['id_user'],
                'nama_lengkap' => $user['nama_lengkap'],
                'username'     => $user['username'],
                'role'         => $user['role']
            ];

            // Catat log aktivitas login
            $log = $pdo->prepare("INSERT INTO tb_log_aktivitas (id_user, aktivitas) VALUES (:id_user, 'Login ke sistem')");
            $log->execute([':id_user' => $user['id_user']]);

            // Redirect sesuai role
            if ($user['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } elseif ($user['role'] === 'petugas') {
                header('Location: petugas/index.php');
            } elseif ($user['role'] === 'owner') {
                header('Location: owner/rekap.php');
            }
            exit;
        } else {
            $error = 'Username atau password salah, atau akun nonaktif!';
        }
    } else {
        $error = 'Harap isi semua bidang input!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Parkir</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-slate-800 border border-slate-700 w-full max-w-md rounded-2xl shadow-2xl p-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold tracking-tight text-emerald-400">Sistem Aplikasi Parkir</h1>
            <p class="text-sm text-slate-400 mt-1">Silakan masuk menggunakan akun terdaftar</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm rounded-lg text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs uppercase font-semibold text-slate-300 mb-1">Username</label>
                <input type="text" name="username" required placeholder="Contoh: petugas1" class="w-full px-4 py-2.5 rounded-lg bg-slate-900 border border-slate-700 focus:outline-none focus:border-emerald-500 text-sm">
            </div>

            <div>
                <label class="block text-xs uppercase font-semibold text-slate-300 mb-1">Password</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full px-4 py-2.5 rounded-lg bg-slate-900 border border-slate-700 focus:outline-none focus:border-emerald-500 text-sm">
            </div>

            <button type="submit" class="w-full py-2.5 bg-emerald-500 hover:bg-emerald-600 transition font-semibold rounded-lg text-slate-950 mt-2 text-sm shadow-lg shadow-emerald-500/20">
                Masuk ke Aplikasi
            </button>
        </form>

        <div class="mt-6 pt-6 border-t border-slate-700/60 text-xs text-slate-400 text-center space-y-1">
            <p>Default Login dari Database:</p>
            <p><span class="text-slate-300 font-mono">admin1</span> / <span class="text-slate-300 font-mono">admin123</span> (Admin)</p>
            <p><span class="text-slate-300 font-mono">petugas1</span> / <span class="text-slate-300 font-mono">petugas123</span> (Petugas)</p>
            <p><span class="text-slate-300 font-mono">owner1</span> / <span class="text-slate-300 font-mono">owner123</span> (Owner)</p>
        </div>
    </div>
</body>
</html>