<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Cahaya Optima Karya</title>

    <link rel="shortcut icon" href="/metronic/assets/media/logos/logo-demo2.png" />
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Feather Icons for eye icon -->
    <script src="https://unpkg.com/feather-icons"></script>

    <style>
        /* Custom Styles & Animations */
        body {
            font-family: 'Inter', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        @keyframes gradient-animation {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .animated-gradient {
            background-size: 200% 200%;
            animation: gradient-animation 15s ease infinite;
        }

        @keyframes fadeInSlideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-container {
            animation: fadeInSlideUp 0.8s ease-out forwards;
        }

        /* Glassmorphism effect */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
    </style>
</head>

<body class="bg-gray-900 text-white">

    <div
        class="min-h-screen flex items-center justify-center animated-gradient bg-gradient-to-br from-gray-900 via-slate-800 to-blue-900 p-4">

        <div class="w-full max-w-6xl mx-auto grid lg:grid-cols-2 gap-10 items-center">

            <!-- Left Side: Branding & Illustration -->
            <div class="hidden lg:block text-center lg:text-left p-8">
                <svg class="w-full h-auto mb-8" viewBox="0 0 500 300" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:rgb(59, 130, 246);stop-opacity:1" />
                            <stop offset="100%" style="stop-color:rgb(129, 140, 248);stop-opacity:1" />
                        </linearGradient>
                        <linearGradient id="grad2" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:rgb(236, 72, 153);stop-opacity:1" />
                            <stop offset="100%" style="stop-color:rgb(168, 85, 247);stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    <path d="M50,250 C50,100 200,50 250,150 C300,250 450,200 450,50" stroke="url(#grad1)" fill="none"
                        stroke-width="5" stroke-linecap="round">
                        <animate attributeName="d" dur="10s" repeatCount="indefinite"
                            values="M50,250 C50,100 200,50 250,150 C300,250 450,200 450,50; M50,50 C50,200 200,250 250,150 C300,50 450,100 450,250; M50,250 C50,100 200,50 250,150 C300,250 450,200 450,50" />
                    </path>
                    <path d="M50,150 C150,250 300,50 450,150" stroke="url(#grad2)" fill="none" stroke-width="5"
                        stroke-linecap="round">
                        <animate attributeName="d" dur="12s" repeatCount="indefinite"
                            values="M50,150 C150,250 300,50 450,150; M50,150 C150,50 300,250 450,150; M50,150 C150,250 300,50 450,150" />
                    </path>
                </svg>
                <h1 class="text-4xl font-bold tracking-tight text-white sm:text-5xl">
                    Selamat Datang,
                </h1>
                <p class="mt-6 text-lg leading-8 text-gray-300">
                    Masuk untuk mengelola dasbor Anda, menganalisis data, dan mengawasi semua aktivitas.
                </p>
            </div>

            <!-- Right Side: Login Form -->
            <div class="w-full max-w-md mx-auto form-container">
                <div class="glass-card rounded-2xl shadow-2xl p-8 sm:p-10">
                    <div class="text-center mb-8">
                        <h2 class="text-2xl sm:text-3xl font-bold text-white">Sign In</h2>
                        <p class="text-gray-400 mt-2">Silakan masukkan kredensial Anda</p>
                    </div>

                    <form method="POST" action="{{ route('login') }}" class="space-y-6">
                        @csrf

                        <!-- Email Address -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-300">Alamat Email</label>
                            <div class="mt-2">
                                <input id="email" name="email" type="email" autocomplete="email" required
                                    value="{{ old('email') }}"
                                    class="block w-full rounded-md border-0 py-3 px-4 bg-gray-700/50 text-white shadow-sm ring-1 ring-inset ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6 transition-all duration-300 @error('email') ring-red-500 @enderror">
                            </div>
                            @error('email')
                                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div>
                            <div class="flex items-center justify-between">
                                <label for="password" class="block text-sm font-medium text-gray-300">Password</label>

                            </div>
                            <div class="mt-2 relative">
                                <input id="password" name="password" type="password" autocomplete="current-password"
                                    required
                                    class="block w-full rounded-md border-0 py-3 px-4 bg-gray-700/50 text-white shadow-sm ring-1 ring-inset ring-gray-600 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6 transition-all duration-300 @error('password') ring-red-500 @enderror">
                                <button type="button" id="togglePassword"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-white">
                                    <i data-feather="eye" class="h-5 w-5"></i>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <input id="remember" name="remember" type="checkbox"
                                    class="h-4 w-4 rounded border-gray-500 bg-gray-700 text-indigo-600 focus:ring-indigo-600">
                                <label for="remember" class="ml-3 block text-sm text-gray-300">Ingat saya</label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div>
                            <button type="submit"
                                class="flex w-full justify-center rounded-md bg-indigo-600 px-3 py-3 text-sm font-semibold leading-6 text-white shadow-lg hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-all duration-300 transform hover:scale-105">
                                Masuk
                            </button>
                        </div>
                    </form>


                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Feather Icons
        feather.replace();

        // Password visibility toggle
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');

        togglePassword.addEventListener('click', function(e) {
            // toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);

            // toggle the eye icon
            const icon = this.querySelector('i');
            if (type === 'password') {
                icon.setAttribute('data-feather', 'eye');
            } else {
                icon.setAttribute('data-feather', 'eye-off');
            }
            feather.replace(); // Re-render icons
        });
    </script>

</body>

</html>
