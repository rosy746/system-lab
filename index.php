<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Management System - Beranda</title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sora': ['Sora', 'sans-serif'],
                        'inter': ['Inter', 'sans-serif'],
                    },
                    colors: {
                        'cream': '#faf8f5',
                        'warm-white': '#ffffff',
                        'text-dark': '#1e3a5f',
                        'text-mid': '#5a5a6e',
                        'text-light': '#9a9aad',
                        'border-color': '#eae8e3',
                        'cyan': '#00c9a7',
                        'cyan-light': '#e6faf5',
                        'blue': '#4a8cff',
                        'blue-light': '#eaf1ff',
                        'pink': '#e8609a',
                        'amber': '#f5a623',
                        'violet': '#8b6bea',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
        }
        
        .animate-fadeInUp {
            animation: fadeInUp 0.8s ease-out forwards;
        }
        
        .animate-fadeInRight {
            animation: fadeInRight 0.8s ease-out forwards;
        }
        
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        .gradient-primary {
            background: linear-gradient(135deg, #1e3a5f 0%, #1a2e4a 60%, #16243b 100%);
        }
        
        .gradient-cyan-blue {
            background: linear-gradient(135deg, #00c9a7, #4a8cff);
        }
        
        .hero-pattern {
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(0, 201, 167, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(74, 140, 255, 0.08) 0%, transparent 50%);
        }
    </style>
</head>
<body class="bg-cream">

    <!-- Navigation -->
    <nav class="bg-warm-white border-b border-border-color sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Logo -->
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 gradient-cyan-blue rounded-xl flex items-center justify-center text-2xl">
                        🧪
                    </div>
                    <div>
                        <h1 class="font-sora text-xl font-bold text-text-dark">Lab Management</h1>
                        <p class="text-xs text-text-light">Sistem Manajemen Laboratorium</p>
                    </div>
                </div>
                
                <!-- Navigation Links -->
                <div class="hidden md:flex items-center gap-8">
                    <a href="#beranda" class="text-text-dark font-medium hover:text-cyan transition-colors">Beranda</a>
                    <a href="#fitur" class="text-text-mid hover:text-cyan transition-colors">Fitur</a>
                    <a href="#tentang" class="text-text-mid hover:text-cyan transition-colors">Tentang</a>
                    <a href="login.php" class="px-6 py-2.5 gradient-primary text-white rounded-xl font-semibold hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300">
                        Masuk
                    </a>
                </div>
                
                <!-- Mobile Menu Button -->
                <button class="md:hidden p-2" onclick="toggleMobileMenu()">
                    <svg class="w-6 h-6 text-text-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div id="mobileMenu" class="hidden md:hidden border-t border-border-color">
            <div class="px-6 py-4 space-y-3">
                <a href="#beranda" class="block text-text-dark font-medium py-2">Beranda</a>
                <a href="#fitur" class="block text-text-mid py-2">Fitur</a>
                <a href="#tentang" class="block text-text-mid py-2">Tentang</a>
                <a href="login.php" class="block px-6 py-2.5 gradient-primary text-white rounded-xl font-semibold text-center">
                    Masuk
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="beranda" class="hero-pattern min-h-screen flex items-center py-20 px-6">
        <div class="max-w-7xl mx-auto w-full">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                
                <!-- Left Content -->
                <div class="animate-fadeInUp space-y-6">
                    <div class="inline-flex items-center gap-2 bg-cyan-light text-cyan px-4 py-2 rounded-full text-sm font-semibold">
                        <span class="w-2 h-2 bg-cyan rounded-full animate-pulse"></span>
                        Sistem Terpadu & Modern
                    </div>
                    
                    <h1 class="font-sora text-5xl lg:text-6xl font-extrabold text-text-dark leading-tight">
                        Kelola Lab Anda dengan
                        <span class="bg-gradient-to-r from-cyan to-blue bg-clip-text text-transparent"> Lebih Mudah</span>
                    </h1>
                    
                    <p class="text-lg text-text-mid leading-relaxed">
                        Sistem manajemen laboratorium yang memudahkan pengelolaan jadwal, inventaris, booking, dan maintenance dalam satu platform yang terintegrasi.
                    </p>
                    
                    <div class="flex flex-wrap gap-4 pt-4">
                        <a href="dashboard.php" class="px-8 py-4 gradient-primary text-white rounded-xl font-semibold text-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-300 inline-flex items-center gap-2">
                            Mulai Sekarang
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                        <a href="#fitur" class="px-8 py-4 bg-warm-white border-2 border-border-color text-text-dark rounded-xl font-semibold text-lg hover:border-text-mid hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                            Pelajari Lebih Lanjut
                        </a>
                    </div>
                    
                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-6 pt-8">
                        <div>
                            <div class="font-sora text-3xl font-bold text-text-dark">24+</div>
                            <div class="text-sm text-text-light mt-1">Total Kelas</div>
                        </div>
                        <div>
                            <div class="font-sora text-3xl font-bold text-text-dark">33+</div>
                            <div class="text-sm text-text-light mt-1">Jadwal Aktif</div>
                        </div>
                        <div>
                            <div class="font-sora text-3xl font-bold text-text-dark">100%</div>
                            <div class="text-sm text-text-light mt-1">Uptime</div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Visual -->
                <div class="animate-fadeInRight relative">
                    <div class="bg-warm-white rounded-3xl p-8 shadow-2xl border border-border-color relative overflow-hidden">
                        <!-- Decorative Elements -->
                        <div class="absolute top-0 right-0 w-40 h-40 bg-cyan opacity-10 rounded-full -mr-20 -mt-20"></div>
                        <div class="absolute bottom-0 left-0 w-32 h-32 bg-blue opacity-10 rounded-full -ml-16 -mb-16"></div>
                        
                        <!-- Icon -->
                        <div class="w-20 h-20 gradient-cyan-blue rounded-2xl flex items-center justify-center text-4xl mb-6 animate-float">
                            🧪
                        </div>
                        
                        <h3 class="font-sora text-2xl font-bold text-text-dark mb-4">Dashboard Preview</h3>
                        <p class="text-text-mid mb-6">Akses semua fitur dalam satu tampilan yang intuitif dan mudah digunakan.</p>
                        
                        <!-- Preview Stats Grid -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-blue-light rounded-xl p-4">
                                <div class="text-3xl mb-2">📅</div>
                                <div class="font-sora text-xl font-bold text-text-dark">Jadwal</div>
                                <div class="text-sm text-text-light">Real-time</div>
                            </div>
                            <div class="bg-cyan-light rounded-xl p-4">
                                <div class="text-3xl mb-2">📦</div>
                                <div class="font-sora text-xl font-bold text-text-dark">Inventaris</div>
                                <div class="text-sm text-text-light">Terpantau</div>
                            </div>
                            <div class="bg-pink-100 rounded-xl p-4">
                                <div class="text-3xl mb-2">🔖</div>
                                <div class="font-sora text-xl font-bold text-text-dark">Booking</div>
                                <div class="text-sm text-text-light">Mudah</div>
                            </div>
                            <div class="bg-amber-50 rounded-xl p-4">
                                <div class="text-3xl mb-2">📊</div>
                                <div class="font-sora text-xl font-bold text-text-dark">Laporan</div>
                                <div class="text-sm text-text-light">Lengkap</div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="fitur" class="bg-warm-white py-20 px-6">
        <div class="max-w-7xl mx-auto">
            
            <!-- Section Header -->
            <div class="text-center mb-16">
                <div class="inline-block text-cyan text-sm font-semibold uppercase tracking-wider mb-3">Fitur Unggulan</div>
                <h2 class="font-sora text-4xl lg:text-5xl font-extrabold text-text-dark mb-4">
                    Semua yang Anda Butuhkan
                </h2>
                <p class="text-lg text-text-mid max-w-2xl mx-auto">
                    Platform lengkap untuk mengelola seluruh aspek laboratorium Anda dengan efisien dan terorganisir.
                </p>
            </div>
            
            <!-- Features Grid -->
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                
                <!-- Feature 1 -->
                <div class="group bg-cream rounded-2xl p-8 border border-transparent hover:border-cyan hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
                    <div class="w-14 h-14 bg-cyan-light rounded-xl flex items-center justify-center text-3xl mb-5 group-hover:scale-110 transition-transform">
                        📅
                    </div>
                    <h3 class="font-sora text-xl font-bold text-text-dark mb-3">Jadwal Lab</h3>
                    <p class="text-text-mid leading-relaxed">
                        Kelola jadwal penggunaan laboratorium komputer dan IPA dengan sistem kalender yang terintegrasi dan real-time.
                    </p>
                </div>
                
                <!-- Feature 2 -->
                <div class="group bg-cream rounded-2xl p-8 border border-transparent hover:border-pink hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
                    <div class="w-14 h-14 bg-pink-100 rounded-xl flex items-center justify-center text-3xl mb-5 group-hover:scale-110 transition-transform">
                        📦
                    </div>
                    <h3 class="font-sora text-xl font-bold text-text-dark mb-3">Inventaris</h3>
                    <p class="text-text-mid leading-relaxed">
                        Pantau semua peralatan dan aset laboratorium dalam satu tempat dengan sistem tracking yang akurat.
                    </p>
                </div>
                
                <!-- Feature 3 -->
                <div class="group bg-cream rounded-2xl p-8 border border-transparent hover:border-amber hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
                    <div class="w-14 h-14 bg-amber-50 rounded-xl flex items-center justify-center text-3xl mb-5 group-hover:scale-110 transition-transform">
                        🔖
                    </div>
                    <h3 class="font-sora text-xl font-bold text-text-dark mb-3">Booking Lab</h3>
                    <p class="text-text-mid leading-relaxed">
                        Reservasi ruang laboratorium untuk acara atau kegiatan khusus dengan proses yang mudah dan cepat.
                    </p>
                </div>
                
                <!-- Feature 4 -->
                <div class="group bg-cream rounded-2xl p-8 border border-transparent hover:border-blue hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
                    <div class="w-14 h-14 bg-blue-light rounded-xl flex items-center justify-center text-3xl mb-5 group-hover:scale-110 transition-transform">
                        🎓
                    </div>
                    <h3 class="font-sora text-xl font-bold text-text-dark mb-3">Manajemen Kelas</h3>
                    <p class="text-text-mid leading-relaxed">
                        Kelola data kelas dan siswa yang menggunakan laboratorium dengan sistem database terintegrasi.
                    </p>
                </div>
                
                <!-- Feature 5 -->
                <div class="group bg-cream rounded-2xl p-8 border border-transparent hover:border-violet hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
                    <div class="w-14 h-14 bg-purple-100 rounded-xl flex items-center justify-center text-3xl mb-5 group-hover:scale-110 transition-transform">
                        🔧
                    </div>
                    <h3 class="font-sora text-xl font-bold text-text-dark mb-3">Maintenance</h3>
                    <p class="text-text-mid leading-relaxed">
                        Catat dan pantau status perawatan dan perbaikan peralatan lab untuk menjaga kualitas operasional.
                    </p>
                </div>
                
                <!-- Feature 6 -->
                <div class="group bg-cream rounded-2xl p-8 border border-transparent hover:border-cyan hover:shadow-xl hover:-translate-y-2 transition-all duration-300">
                    <div class="w-14 h-14 bg-cyan-light rounded-xl flex items-center justify-center text-3xl mb-5 group-hover:scale-110 transition-transform">
                        📊
                    </div>
                    <h3 class="font-sora text-xl font-bold text-text-dark mb-3">Laporan & Analitik</h3>
                    <p class="text-text-mid leading-relaxed">
                        Lihat statistik dan laporan penggunaan laboratorium secara lengkap dengan visualisasi data yang informatif.
                    </p>
                </div>
                
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="tentang" class="bg-cream py-20 px-6">
        <div class="max-w-7xl mx-auto">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                
                <!-- Left Content -->
                <div>
                    <div class="inline-block text-cyan text-sm font-semibold uppercase tracking-wider mb-3">Tentang Sistem</div>
                    <h2 class="font-sora text-4xl lg:text-5xl font-extrabold text-text-dark mb-6">
                        Solusi Modern untuk Manajemen Laboratorium
                    </h2>
                    <div class="space-y-4 text-text-mid leading-relaxed">
                        <p>
                            Lab Management System adalah platform terpadu yang dirancang khusus untuk memudahkan pengelolaan laboratorium pendidikan. Dengan antarmuka yang intuitif dan fitur-fitur lengkap, sistem ini membantu meningkatkan efisiensi operasional laboratorium Anda.
                        </p>
                        <p>
                            Dikembangkan dengan teknologi modern dan mengutamakan kemudahan penggunaan, sistem ini telah membantu banyak institusi pendidikan dalam mengoptimalkan pengelolaan sumber daya laboratorium mereka.
                        </p>
                    </div>
                    
                    <div class="mt-8 space-y-4">
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-cyan-light rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-cyan" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-semibold text-text-dark mb-1">Interface yang User-Friendly</h4>
                                <p class="text-text-mid text-sm">Mudah digunakan bahkan untuk pengguna pemula</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-blue-light rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-blue" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-semibold text-text-dark mb-1">Sistem Terintegrasi</h4>
                                <p class="text-text-mid text-sm">Semua fitur terhubung dalam satu platform</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-pink-100 rounded-lg flex items-center justify-center flex-shrink-0">
                                <svg class="w-6 h-6 text-pink" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-semibold text-text-dark mb-1">Akses Real-Time</h4>
                                <p class="text-text-mid text-sm">Data selalu update dan dapat diakses kapan saja</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Image/Visual -->
                <div class="relative">
                    <div class="bg-warm-white rounded-3xl p-8 shadow-2xl">
                        <div class="rounded-2xl w-full h-80 bg-gradient-to-br from-cyan-light to-blue-light flex items-center justify-center">
                            <div class="text-center">
                                <div class="text-7xl mb-4">🔬</div>
                                <h3 class="font-sora text-2xl font-bold text-text-dark">Lab Management</h3>
                                <p class="text-text-mid mt-2">Modern & Efficient</p>
                            </div>
                        </div>
                        
                        <!-- Floating Card -->
                        <div class="absolute -bottom-6 -left-6 bg-warm-white rounded-2xl p-6 shadow-xl border border-border-color">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 gradient-cyan-blue rounded-xl flex items-center justify-center text-white text-xl font-bold">
                                    ✓
                                </div>
                                <div>
                                    <div class="font-sora text-2xl font-bold text-text-dark">100%</div>
                                    <div class="text-sm text-text-light">Sistem Uptime</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-warm-white py-20 px-6">
        <div class="max-w-4xl mx-auto text-center">
            <div class="gradient-primary rounded-3xl p-12 lg:p-16 relative overflow-hidden">
                <!-- Decorative circles -->
                <div class="absolute top-0 right-0 w-64 h-64 bg-cyan opacity-10 rounded-full -mr-32 -mt-32"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-blue opacity-10 rounded-full -ml-24 -mb-24"></div>
                
                <div class="relative z-10">
                    <h2 class="font-sora text-3xl lg:text-4xl font-extrabold text-white mb-4">
                        Siap Mengelola Lab dengan Lebih Baik?
                    </h2>
                    <p class="text-lg text-white opacity-90 mb-8 max-w-2xl mx-auto">
                        Bergabunglah dengan institusi lainnya yang telah menggunakan Lab Management System untuk meningkatkan efisiensi laboratorium mereka.
                    </p>
                    
                    <div class="flex flex-wrap gap-4 justify-center">
                        <a href="dashboard.php" class="px-8 py-4 bg-white text-text-dark rounded-xl font-semibold text-lg hover:shadow-xl hover:-translate-y-1 transition-all duration-300 inline-flex items-center gap-2">
                            Akses Dashboard
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                            </svg>
                        </a>
                        <a href="login.php" class="px-8 py-4 bg-transparent border-2 border-white text-white rounded-xl font-semibold text-lg hover:bg-white hover:text-text-dark hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                            Login Sekarang
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-text-dark text-white py-12 px-6">
        <div class="max-w-7xl mx-auto">
            <div class="grid md:grid-cols-4 gap-8 mb-8">
                
                <!-- Brand -->
                <div class="md:col-span-2">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-12 h-12 gradient-cyan-blue rounded-xl flex items-center justify-center text-2xl">
                            🧪
                        </div>
                        <div>
                            <h3 class="font-sora text-lg font-bold">Lab Management</h3>
                            <p class="text-sm text-gray-400">Sistem Manajemen Laboratorium</p>
                        </div>
                    </div>
                    <p class="text-gray-400 text-sm leading-relaxed max-w-md">
                        Platform terpadu untuk mengelola seluruh aspek laboratorium pendidikan dengan efisien dan terorganisir.
                    </p>
                </div>
                
                <!-- Links -->
                <div>
                    <h4 class="font-semibold mb-4">Menu</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="#beranda" class="hover:text-cyan transition-colors">Beranda</a></li>
                        <li><a href="#fitur" class="hover:text-cyan transition-colors">Fitur</a></li>
                        <li><a href="#tentang" class="hover:text-cyan transition-colors">Tentang</a></li>
                        <li><a href="dashboard.php" class="hover:text-cyan transition-colors">Dashboard</a></li>
                    </ul>
                </div>
                
                <!-- Contact -->
                <div>
                    <h4 class="font-semibold mb-4">Kontak</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li>Email: info@labmanagement.edu</li>
                        <li>Telp: (021) 1234-5678</li>
                        <li>Support: support@labmanagement.edu</li>
                    </ul>
                </div>
                
            </div>
            
            <div class="border-t border-gray-700 pt-8 text-center text-sm text-gray-400">
                <p>&copy; 2026 Lab Management System. Built with ❤️ for better education.</p>
            </div>
        </div>
    </footer>

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
        }
        
        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    // Close mobile menu if open
                    document.getElementById('mobileMenu').classList.add('hidden');
                }
            });
        });
    </script>

</body>
</html>