<?php
session_start();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --cream: #faf8f5;
            --warm-white: #ffffff;
            --text-dark: #1e1e2e;
            --text-mid: #5a5a6e;
            --text-light: #9a9aad;
            --border: #eae8e3;

            --cyan: #00c9a7;
            --cyan-light: #e6faf5;
            --pink: #e8609a;
            --pink-light: #fde9f1;
            --amber: #f5a623;
            --amber-light: #fef3e0;
            --blue: #4a8cff;
            --blue-light: #eaf1ff;
            --violet: #8b6bea;
            --violet-light: #f0ecfd;
            --coral: #ff6f61;
            --coral-light: #fff0ee;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--text-dark);
            min-height: 100vh;
        }

        /* ─── Top Bar ─── */
        .topbar {
            background: var(--warm-white);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .topbar-logo {
            width: 42px;
            height: 42px;
            background: linear-gradient(135deg, var(--cyan), var(--blue));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .topbar-text h1 {
            font-family: 'Sora', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.2;
        }

        .topbar-text span {
            font-size: 0.78rem;
            color: var(--text-light);
            font-weight: 400;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .topbar-date {
            font-size: 0.82rem;
            color: var(--text-light);
            text-align: right;
            line-height: 1.4;
        }

        .topbar-date strong {
            color: var(--text-mid);
            font-weight: 600;
        }

        .topbar-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--pink), var(--coral));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: #fff;
            font-weight: 600;
        }

        /* ─── Main Layout ─── */
        .main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2.2rem 2rem 3rem;
        }

        /* ─── Welcome Banner ─── */
        .welcome-banner {
            background: linear-gradient(135deg, #1e3a5f 0%, #1a2e4a 60%, #16243b 100%);
            border-radius: 20px;
            padding: 2rem 2.2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2.2rem;
            position: relative;
            overflow: hidden;
            color: #fff;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -60px;
            right: -60px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(0,201,167,0.12);
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -40px;
            right: 140px;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: rgba(74,140,255,0.1);
        }

        .welcome-text {
            position: relative;
            z-index: 1;
        }

        .welcome-text h2 {
            font-family: 'Sora', sans-serif;
            font-size: 1.65rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .welcome-text p {
            font-size: 0.9rem;
            opacity: 0.7;
            font-weight: 400;
        }

        .welcome-emoji {
            font-size: 2.8rem;
            position: relative;
            z-index: 1;
            animation: gentle-bob 3s ease-in-out infinite;
        }

        @keyframes gentle-bob {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        /* ─── Section Label ─── */
        .section-label {
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-light);
            margin-bottom: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .section-label::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        /* ─── Menu Grid ─── */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.1rem;
            margin-bottom: 2.5rem;
        }

        .menu-card {
            background: var(--warm-white);
            border: 1.5px solid var(--border);
            border-radius: 16px;
            padding: 1.6rem 1.5rem 1.4rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            position: relative;
            opacity: 0;
            transform: translateY(18px);
            animation: cardIn 0.45s ease forwards;
        }

        .menu-card:nth-child(1) { animation-delay: 0.08s; }
        .menu-card:nth-child(2) { animation-delay: 0.14s; }
        .menu-card:nth-child(3) { animation-delay: 0.20s; }
        .menu-card:nth-child(4) { animation-delay: 0.26s; }
        .menu-card:nth-child(5) { animation-delay: 0.32s; }
        .menu-card:nth-child(6) { animation-delay: 0.38s; }

        @keyframes cardIn {
            to { opacity: 1; transform: translateY(0); }
        }

        .menu-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 28px rgba(0,0,0,0.08);
        }

        /* Colour themes per card */
        .menu-card[data-color="cyan"]:hover { border-color: var(--cyan); }
        .menu-card[data-color="pink"]:hover { border-color: var(--pink); }
        .menu-card[data-color="amber"]:hover { border-color: var(--amber); }
        .menu-card[data-color="blue"]:hover { border-color: var(--blue); }
        .menu-card[data-color="violet"]:hover { border-color: var(--violet); }
        .menu-card[data-color="coral"]:hover { border-color: var(--coral); }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .card-icon-wrap {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: transform 0.3s ease;
        }

        .menu-card:hover .card-icon-wrap {
            transform: scale(1.08);
        }

        .card-icon-wrap[data-color="cyan"]  { background: var(--cyan-light); }
        .card-icon-wrap[data-color="pink"]   { background: var(--pink-light); }
        .card-icon-wrap[data-color="amber"]  { background: var(--amber-light); }
        .card-icon-wrap[data-color="blue"]   { background: var(--blue-light); }
        .card-icon-wrap[data-color="violet"] { background: var(--violet-light); }
        .card-icon-wrap[data-color="coral"]  { background: var(--coral-light); }

        .card-badge {
            font-size: 0.68rem;
            font-weight: 600;
            padding: 0.22rem 0.6rem;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-popular { background: var(--amber-light); color: var(--amber); }
        .badge-new      { background: var(--cyan-light);  color: var(--cyan); }

        .card-body h3 {
            font-family: 'Sora', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
        }

        .card-body p {
            font-size: 0.82rem;
            color: var(--text-light);
            line-height: 1.5;
        }

        .card-arrow {
            font-size: 0.95rem;
            color: var(--text-light);
            transition: transform 0.25s, color 0.25s;
            align-self: flex-end;
        }

        .menu-card:hover .card-arrow {
            transform: translateX(4px);
            color: var(--text-mid);
        }

        /* ─── Stats Row ─── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.1rem;
            margin-bottom: 2.5rem;
        }

        .stat-card {
            background: var(--warm-white);
            border: 1.5px solid var(--border);
            border-radius: 16px;
            padding: 1.3rem 1.2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            opacity: 0;
            animation: cardIn 0.45s ease forwards;
        }

        .stat-card:nth-child(1) { animation-delay: 0.42s; }
        .stat-card:nth-child(2) { animation-delay: 0.48s; }
        .stat-card:nth-child(3) { animation-delay: 0.54s; }
        .stat-card:nth-child(4) { animation-delay: 0.60s; }

        .stat-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }

        .stat-icon.s-blue   { background: var(--blue-light); }
        .stat-icon.s-cyan   { background: var(--cyan-light); }
        .stat-icon.s-pink   { background: var(--pink-light); }
        .stat-icon.s-amber  { background: var(--amber-light); }

        .stat-info {
            display: flex;
            flex-direction: column;
        }

        .stat-number {
            font-family: 'Sora', sans-serif;
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.1;
        }

        .stat-label {
            font-size: 0.76rem;
            color: var(--text-light);
            margin-top: 0.15rem;
            font-weight: 500;
        }

        /* ─── Helper / Info Strip ─── */
        .info-strip {
            background: var(--warm-white);
            border: 1.5px solid var(--border);
            border-radius: 16px;
            padding: 1.2rem 1.6rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            opacity: 0;
            animation: cardIn 0.45s 0.68s ease forwards;
        }

        .info-strip-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--blue-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .info-strip-text {
            font-size: 0.84rem;
            color: var(--text-mid);
            line-height: 1.5;
        }

        .info-strip-text strong {
            color: var(--text-dark);
            font-weight: 600;
        }

        /* ─── Footer ─── */
        footer {
            text-align: center;
            padding: 1.8rem 2rem 2.2rem;
            font-size: 0.78rem;
            color: var(--text-light);
        }

        /* ─── Responsive ─── */
        @media (max-width: 860px) {
            .menu-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 540px) {
            .main { padding: 1.4rem 1rem 2.5rem; }
            .menu-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
            .welcome-banner { flex-direction: column; align-items: flex-start; gap: 0.6rem; padding: 1.6rem; }
            .welcome-emoji { font-size: 2rem; }
            .topbar { padding: 0.85rem 1rem; }
        }
    </style>
</head>
<body>

<!-- ─── Top Bar ─── -->
<div class="topbar">
    <div class="topbar-left">
        <div class="topbar-logo">🧪</div>
        <div class="topbar-text">
            <h1>Lab Management</h1>
            <span>Sistem Manajemen Laboratorium</span>
        </div>
    </div>
    <div class="topbar-right">
        <div class="topbar-date">
            <strong id="js-date">—</strong><br>
            <span id="js-time">—</span>
        </div>
        <div class="topbar-avatar">A</div>
    </div>
</div>

<!-- ─── Main ─── -->
<div class="main">

    <!-- Welcome -->
    <div class="welcome-banner">
        <div class="welcome-text">
            <h2>Selamat Datang di Sistem Lab</h2>
            <p>Pilih menu di bawah untuk mulai mengelola laboratorium Anda.</p>
        </div>
        <div class="welcome-emoji">👋</div>
    </div>

    <!-- Stats -->
    <div class="section-label">Ringkasan Hari Ini</div>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon s-blue">🎓</div>
            <div class="stat-info">
                <div class="stat-number">24</div>
                <div class="stat-label">Total Kelas</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon s-cyan">📅</div>
            <div class="stat-info">
                <div class="stat-number">33</div>
                <div class="stat-label">Jadwal Aktif</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon s-pink">🧪</div>
            <div class="stat-info">
                <div class="stat-number">2</div>
                <div class="stat-label">Laboratorium</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon s-amber">✅</div>
            <div class="stat-info">
                <div class="stat-number">100%</div>
                <div class="stat-label">Uptime</div>
            </div>
        </div>
    </div>

    <!-- Menu -->
    <div class="section-label">Menu Utama</div>
    <div class="menu-grid">

        <a href="schedules.php" class="menu-card" data-color="cyan">
            <div class="card-top">
                <div class="card-icon-wrap" data-color="cyan">📅</div>
            </div>
            <div class="card-body">
                <h3>Jadwal Lab</h3>
                <p>Lihat dan kelola jadwal penggunaan laboratorium komputer dan IPA secara mudah.</p>
            </div>
            <span class="card-arrow">→</span>
        </a>

        <a href="inventory_list.php" class="menu-card" data-color="pink">
            <div class="card-top">
                <div class="card-icon-wrap" data-color="pink">📦</div>
            </div>
            <div class="card-body">
                <h3>Inventaris</h3>
                <p>Pantau semua peralatan dan aset laboratorium dalam satu tempat.</p>
            </div>
            <span class="card-arrow">→</span>
        </a>

        <a href="bookings.php" class="menu-card" data-color="amber">
            <div class="card-top">
                <div class="card-icon-wrap" data-color="amber">🔖</div>
                <span class="card-badge badge-popular">Popular</span>
            </div>
            <div class="card-body">
                <h3>Booking Lab</h3>
                <p>Reservasi ruang laboratorium untuk acara atau kegiatan khusus Anda.</p>
            </div>
            <span class="card-arrow">→</span>
        </a>

        <a href="classes.php" class="menu-card" data-color="blue">
            <div class="card-top">
                <div class="card-icon-wrap" data-color="blue">🎓</div>
            </div>
            <div class="card-body">
                <h3>Kelas</h3>
                <p>Kelola data kelas dan siswa yang menggunakan laboratorium.</p>
            </div>
            <span class="card-arrow">→</span>
        </a>

        <a href="maintenance.php" class="menu-card" data-color="violet">
            <div class="card-top">
                <div class="card-icon-wrap" data-color="violet">🔧</div>
                <span class="card-badge badge-new">Baru</span>
            </div>
            <div class="card-body">
                <h3>Maintenance</h3>
                <p>Catat dan pantau status perawatan dan perbaikan peralatan lab.</p>
            </div>
            <span class="card-arrow">→</span>
        </a>

        <a href="reports.php" class="menu-card" data-color="coral">
            <div class="card-top">
                <div class="card-icon-wrap" data-color="coral">📊</div>
            </div>
            <div class="card-body">
                <h3>Laporan</h3>
                <p>Lihat statistik dan laporan penggunaan laboratorium secara lengkap.</p>
            </div>
            <span class="card-arrow">→</span>
        </a>

    </div>

    <!-- Info strip -->
    <div class="info-strip">
        <div class="info-strip-icon">💡</div>
        <div class="info-strip-text">
            <strong>Tips:</strong> Gunakan menu <strong>Booking Lab</strong> untuk memesan laboratorium, dan <strong>Jadwal Lab</strong> untuk mengecek jadwal yang sudah ada. Butuh bantuan? Hubungi tim IT.
        </div>
    </div>

</div>

<!-- Footer -->
<footer>
    &copy; 2026 Lab Management System &nbsp;·&nbsp; Built with ❤️ for better education.
</footer>

<!-- ─── Script ─── -->
<script>
    // Live clock
    function updateClock() {
        const now = new Date();
        const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        document.getElementById('js-date').textContent =
            days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()];
        document.getElementById('js-time').textContent =
            now.getHours().toString().padStart(2,'0') + ':' +
            now.getMinutes().toString().padStart(2,'0');
    }
    updateClock();
    setInterval(updateClock, 1000);
</script>

</body>
</html>