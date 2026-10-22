<?php
include('../backend/session_init.php');
include('../backend/db_config.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authentication Pending | CPMMIS</title>
    <link rel="icon" type="image/png" href="../chmsu.png">
    <meta name="color-scheme" content="light">
    <style>html{background:#F0F2F5;color-scheme:light}</style>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        .bg-mesh {
            background-color: #F0F2F5;
            background-image: 
                radial-gradient(at 0% 0%, rgba(37, 99, 235, 0.2) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(79, 70, 229, 0.2) 0px, transparent 50%);
        }

        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .float-icon { animation: float 3s ease-in-out infinite; }
        
        .pulse-border {
            box-shadow: 0 0 0 0 rgba(66, 103, 178, 0.4);
            animation: pulse-border 2s infinite;
        }
        @keyframes pulse-border {
            70% { box-shadow: 0 0 0 20px rgba(66, 103, 178, 0); }
            100% { box-shadow: 0 0 0 0 rgba(66, 103, 178, 0); }
        }
    </style>
</head>
<body class="bg-mesh min-h-screen flex items-center justify-center p-6 overflow-hidden">

    <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none opacity-20">
        <div class="absolute -top-24 -left-24 w-96 h-96 bg-blue-600 rounded-full blur-[120px]"></div>
        <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-blue-600 rounded-full blur-[120px]"></div>
    </div>

    <div class="max-w-md w-full relative">
        <div class="flex justify-center mb-10">
            <div class="flex items-center gap-3 bg-slate-100 backdrop-blur-md px-6 py-3 rounded-full border border-gray-200">
                <img src="../chmsu.png" class="w-8 h-8 object-contain">
                <span class="text-slate-900 font-black uppercase tracking-tighter text-sm">CPMMIS Secure Gate</span>
            </div>
        </div>

        <div class="bg-white rounded-[3.5rem] shadow-2xl p-10 text-center relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-blue-600 to-blue-500"></div>

            <div class="relative inline-block mb-8">
                <div class="w-24 h-24 bg-blue-50 text-blue-500 rounded-[2.5rem] flex items-center justify-center text-4xl pulse-border relative z-10">
                    <i class="fas fa-shield-halved float-icon"></i>
                </div>
                <div class="absolute inset-0 border-2 border-dashed border-blue-200 rounded-full animate-[spin_10s_linear_infinite]"></div>
            </div>

            <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tighter mb-4">Identity Verification</h1>
            <p class="text-slate-500 font-bold text-[11px] leading-relaxed uppercase tracking-[0.2em] mb-10 px-2">
                Your group registration is currently <span class="text-blue-600">Pending Review</span>. Administrative authorization is required to access the Capstone Repository.
            </p>

            <div class="space-y-3 mb-10">
                <div class="flex items-center justify-between p-4 rounded-2xl bg-blue-50 border border-blue-100">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-blue-500 text-xs"></i>
                        <span class="text-[10px] font-black text-blue-700 uppercase tracking-widest">Account Created</span>
                    </div>
                    <span class="text-[8px] font-black text-blue-500 uppercase">Verified</span>
                </div>

                <div class="flex items-center justify-between p-4 rounded-2xl bg-blue-50 border border-blue-100">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-clock text-blue-500 text-xs animate-spin-slow"></i>
                        <span class="text-[10px] font-black text-blue-700 uppercase tracking-widest">Admin Approval</span>
                    </div>
                    <span class="text-[8px] font-black text-blue-500 uppercase">Processing</span>
                </div>
            </div>

            <div class="space-y-4">
                <button onclick="location.reload()" class="w-full py-4 bg-slate-100 text-slate-600 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-200 transition">
                    <i class="fas fa-sync-alt mr-2"></i> Refresh Status
                </button>
                <a href="../backend/auth/logout.php?csrf_token=<?php echo generate_csrf_token(); ?>" class="block w-full py-4 bg-slate-100 text-slate-600 border border-gray-200 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-red-600 hover:text-white transition shadow-xl shadow-slate-200">
                    Secure Log Out
                </a>
            </div>
        </div>

        <p class="text-center mt-8 text-slate-600 font-bold text-[9px] uppercase tracking-widest leading-loose">
            Usually verified within 24 hours.<br>
            For urgent access, contact the system administrator.
        </p>
    </div>

    <script>
        // Smooth entrance animation
        document.addEventListener('DOMContentLoaded', () => {
            const card = document.querySelector('.bg-white');
            card.style.opacity = '0';
            card.style.transform = 'scale(0.9) translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1)';
                card.style.opacity = '1';
                card.style.transform = 'scale(1) translateY(0)';
            }, 100);
        });
    </script>
<style>
@keyframes globalHighlightFade {
    0% { background-color: rgba(59, 130, 246, 0.2) !important; box-shadow: inset 0 0 0 2px #3b82f6 !important; border-radius: inherit; }
    100% { background-color: transparent; box-shadow: inset 0 0 0 0px transparent; }
}
.global-highlight-row { animation: globalHighlightFade 3s ease-in-out forwards !important; }
</style>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const highlightId = urlParams.get('highlight');
    if (highlightId) {
        const el = document.getElementById('row-' + highlightId);
        if (el) {
            setTimeout(() => {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.classList.add('global-highlight-row');
            }, 100);
        }
    }
});
</script>
</body>
</html>