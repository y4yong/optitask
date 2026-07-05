<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OptiTask - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Stack+Sans+Headline:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <style>
        body { font-family: 'Stack Sans Headline', system-ui, -apple-system, sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Stack Sans Headline', system-ui, -apple-system, sans-serif; }
        .pink-bg { background-color: #FFF5F7; }
        .soft-pink-btn { 
            background: #FF8FAB; 
            box-shadow: 0 4px 15px rgba(255, 143, 171, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .soft-pink-btn:hover { 
            background: #FB6F92; 
            transform: scale(1.02);
            box-shadow: 0 8px 25px rgba(251, 111, 146, 0.4);
        }
        .input-box {
            border: 2px solid #FFE5EC;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .input-box:focus-within {
            border-color: #FF8FAB;
            background-color: #FFF;
            box-shadow: 0 0 0 4px rgba(255, 143, 171, 0.15);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 228, 234, 0.6);
            border-radius: 2.5rem;
            box-shadow: 0 20px 40px rgba(251, 111, 146, 0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .glass-card:hover {
            transform: translateY(-4px);
            border-color: rgba(251, 111, 146, 0.3);
            box-shadow: 0 30px 60px rgba(251, 111, 146, 0.07);
        }
    </style>
</head>
<body class="pink-bg flex items-center justify-center min-h-screen p-6">

    <div class="glass-card p-10 w-full max-w-md">
        <div class="flex flex-col items-center mb-8">
            <div class="w-16 h-16 bg-[#FFB3C6] rounded-2xl flex items-center justify-center shadow-md mb-4">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="4" y="4" width="7" height="7" rx="2" fill="white"/>
                    <rect x="4" y="13" width="7" height="7" rx="2" fill="white"/>
                    <rect x="13" y="4" width="7" height="7" rx="2" fill="white"/>
                    <rect x="13" y="13" width="7" height="7" rx="2" fill="#FF8FAB"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 tracking-tight text-center">OptiTask</h1>
            <p class="text-gray-400 text-sm font-medium uppercase tracking-widest text-center">Login</p>
        </div>

        <div class="flex bg-[#FDE2E4] p-1.5 rounded-2xl mb-8">
            <button class="w-1/2 text-center py-2.5 text-sm font-bold bg-white text-[#FB6F92] shadow-sm rounded-xl">Login</button>
            <a href="{{ route('signup') }}" class="w-1/2 text-center py-2.5 text-sm font-bold text-[#FF8FAB]">Sign Up</a>
        </div>

        @if($errors->any())
            <div class="mb-6 p-4 rounded-2xl text-center text-sm font-semibold bg-red-50 text-red-400">
                {{ $errors->first() }}
            </div>
        @endif

        @if(session('success'))
            <div class="mb-6 p-4 rounded-2xl text-center text-sm font-semibold bg-green-50 text-green-500">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST" class="space-y-6">
            @csrf
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Worker ID</label>
                <input type="text" name="worker_id" value="{{ old('worker_id') }}" required placeholder="e.g. EM001, MG042" 
                    class="w-full px-5 py-4 rounded-2xl bg-[#FBFBFC] input-box text-sm text-gray-600 outline-none">
            </div>

            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2 ml-1">Password</label>
                <div class="relative flex items-center bg-[#FBFBFC] rounded-2xl input-box">
                    <input type="password" id="login_password" name="password" required placeholder="••••••••" 
                        class="w-full px-5 py-4 rounded-2xl bg-transparent text-sm text-gray-600 outline-none">
                    <button type="button" onclick="togglePassword()" class="absolute right-4 text-pink-300 flex items-center">
                        <ion-icon id="eye-icon" name="eye-off-outline" class="text-xl"></ion-icon>
                    </button>
                </div>
            </div>

            <button type="submit" class="w-full soft-pink-btn text-white py-4 rounded-2xl font-bold text-md mt-2">
                Login
            </button>
        </form>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('login_password');
            const eyeIcon = document.getElementById('eye-icon');
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                eyeIcon.setAttribute("name", "eye-outline");
            } else {
                passwordInput.type = "password";
                eyeIcon.setAttribute("name", "eye-off-outline");
            }
        }
    </script>
</body>
</html>
