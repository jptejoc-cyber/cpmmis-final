<div id="customConfirmModal" class="fixed inset-0 bg-black/80 backdrop-blur-md hidden items-center justify-center z-[1000] p-4 opacity-0 transition-opacity duration-300">
    <div class="bg-gradient-to-b from-white to-slate-100 rounded-[2rem] w-full max-w-[400px] shadow-2xl overflow-hidden border border-gray-200 transform scale-95 transition-transform duration-300" id="customConfirmContent">
        <div class="px-8 pt-8 pb-6 text-center">
            <div class="w-20 h-20 bg-slate-100 rounded-full mx-auto flex items-center justify-center mb-6" id="confirmIconBg">
                <i class="fas fa-exclamation-triangle text-3xl text-rose-500" id="confirmIcon"></i>
            </div>
            <h3 id="confirmTitle" class="text-xl font-black text-slate-900 uppercase tracking-tight mb-2">Are you sure?</h3>
            <p id="confirmMessage" class="text-[10px] font-bold text-slate-500 uppercase tracking-widest leading-relaxed px-4">This action cannot be undone.</p>
        </div>
        <div class="px-8 pb-8 flex gap-3">
            <button onclick="closeCustomConfirm()" class="flex-1 py-4 bg-slate-100 text-slate-600 rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-slate-100 transition-all border border-gray-200">Cancel</button>
            <button id="confirmActionBtn" class="flex-1 py-4 bg-rose-600 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-rose-700 shadow-lg shadow-rose-900 transition-all border border-rose-500/30 active:scale-95">Confirm</button>
        </div>
    </div>
</div>

<script>
    let currentConfirmCallback = null;

    function showCustomConfirm(title, message, type, confirmText, callback) {
        document.getElementById('confirmTitle').innerText = title;
        document.getElementById('confirmMessage').innerText = message;
        
        const iconBg = document.getElementById('confirmIconBg');
        const icon = document.getElementById('confirmIcon');
        const btn = document.getElementById('confirmActionBtn');
        btn.innerText = confirmText || 'Confirm';
        
        if (type === 'danger') {
            iconBg.className = 'w-20 h-20 bg-rose-500/10 rounded-full mx-auto flex items-center justify-center mb-6 border border-rose-500/20';
            icon.className = 'fas fa-exclamation-triangle text-3xl text-rose-500 drop-shadow-lg';
            btn.className = 'flex-1 py-4 bg-gradient-to-r from-rose-600 to-red-500 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:brightness-110 shadow-lg shadow-rose-900/50 transition-all border border-rose-500/30 active:scale-95';
        } else if (type === 'success') {
            iconBg.className = 'w-20 h-20 bg-blue-500/10 rounded-full mx-auto flex items-center justify-center mb-6 border border-blue-500/20';
            icon.className = 'fas fa-check text-3xl text-blue-500 drop-shadow-lg';
            btn.className = 'flex-1 py-4 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:brightness-110 shadow-lg shadow-blue-900/50 transition-all border border-blue-500/30 active:scale-95';
        } else {
            iconBg.className = 'w-20 h-20 bg-blue-500/10 rounded-full mx-auto flex items-center justify-center mb-6 border border-blue-500/20';
            icon.className = 'fas fa-question text-3xl text-blue-500 drop-shadow-lg';
            btn.className = 'flex-1 py-4 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:brightness-110 shadow-lg shadow-blue-900/50 transition-all border border-blue-500/30 active:scale-95';
        }
        
        currentConfirmCallback = callback;
        
        const modal = document.getElementById('customConfirmModal');
        const content = document.getElementById('customConfirmContent');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Trigger reflow
        void modal.offsetWidth;
        
        modal.classList.remove('opacity-0');
        content.classList.remove('scale-95');
    }

    function closeCustomConfirm() {
        const modal = document.getElementById('customConfirmModal');
        const content = document.getElementById('customConfirmContent');
        modal.classList.add('opacity-0');
        content.classList.add('scale-95');
        
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            currentConfirmCallback = null;
        }, 300);
    }

    document.getElementById('confirmActionBtn').addEventListener('click', () => {
        if (currentConfirmCallback) {
            currentConfirmCallback();
        }
    });
</script>
