<div id="archive-contents-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-[540] items-center justify-center p-4" onclick="if(event.target===this)closeArchiveContents()">
    <div id="archive-contents-panel" role="dialog" aria-modal="true" aria-labelledby="archive-contents-title" class="w-full max-w-md max-h-[80vh] bg-white rounded-[2rem] border border-gray-200 shadow-2xl overflow-hidden flex flex-col transform transition-all scale-95 opacity-0">
        <div class="p-5 bg-slate-100 border-b border-gray-200 flex items-start justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 bg-blue-500/10 text-blue-500 rounded-xl flex items-center justify-center shrink-0"><i class="fas fa-file-pdf text-sm"></i></div>
                <div class="min-w-0">
                    <p class="text-[8px] font-black uppercase tracking-widest text-blue-500">Archive Contents</p>
                    <h3 id="archive-contents-title" class="text-sm font-black uppercase text-slate-900 tracking-tight leading-tight break-words">Project PDFs</h3>
                </div>
            </div>
            <button onclick="closeArchiveContents()" class="w-8 h-8 rounded-xl bg-white text-slate-500 hover:text-red-500 transition shrink-0" aria-label="Close archive contents"><i class="fas fa-times text-xs"></i></button>
        </div>
        <div id="archive-contents-list" class="flex-1 overflow-y-auto custom-scrollbar p-3 space-y-2 min-h-[140px]"></div>
        <div class="px-5 py-3 border-t border-gray-200 bg-white">
            <p class="text-[8px] font-bold uppercase tracking-widest text-slate-400 text-center">Select a PDF to open the viewer</p>
        </div>
    </div>
</div>

<script>
    var archiveContentsId = null;
    var archiveContentsKind = '';

    function archiveContentsMessage(message, isError) {
        var list = document.getElementById('archive-contents-list');
        if (!list) return;
        list.innerHTML = '';
        var box = document.createElement('div');
        box.className = 'flex flex-col items-center justify-center text-center py-10 px-4';
        var icon = document.createElement('i');
        icon.className = (isError ? 'fas fa-exclamation-circle text-rose-400' : 'fas fa-file-pdf text-slate-400') + ' text-2xl mb-3';
        var text = document.createElement('p');
        text.className = 'text-[9px] font-black uppercase tracking-widest leading-relaxed ' + (isError ? 'text-rose-500' : 'text-slate-500');
        text.textContent = message;
        box.append(icon, text);
        list.appendChild(box);
    }

    function formatArchiveFileSize(bytes) {
        var size = Number(bytes) || 0;
        if (size <= 0) return 'PDF';
        if (size < 1024) return size + ' B';
        if (size < 1024 * 1024) return (size / 1024).toFixed(1) + ' KB';
        return (size / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function openArchiveContents(id, title) {
        archiveContentsId = id;
        archiveContentsKind = '';
        var modal = document.getElementById('archive-contents-modal');
        var panel = document.getElementById('archive-contents-panel');
        if (!modal || !panel) return;
        document.getElementById('archive-contents-title').textContent = title || 'Project PDFs';
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(function() {
            panel.classList.remove('scale-95', 'opacity-0');
            panel.classList.add('scale-100', 'opacity-100');
        }, 10);
        document.getElementById('archive-contents-list').innerHTML = '<div class="flex items-center justify-center gap-3 py-10 text-slate-500"><i class="fas fa-circle-notch fa-spin text-blue-500"></i><span class="text-[9px] font-black uppercase tracking-widest">Loading PDF files...</span></div>';

        fetch('../backend/archive/list_archive_pdfs.php?id=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
            .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
            .then(function(result) {
                if (!result.ok || !result.data || result.data.status !== 'success') {
                    throw new Error((result.data && result.data.message) || 'Unable to load archive contents.');
                }
                var data = result.data;
                archiveContentsKind = data.kind;
                var list = document.getElementById('archive-contents-list');
                list.innerHTML = '';

                if (!data.entries || data.entries.length === 0) {
                    archiveContentsMessage(data.message || 'No PDF documents were found inside this archive.');
                    return;
                }

                data.entries.forEach(function(entry) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'w-full flex items-center gap-3 p-3 rounded-2xl bg-slate-50 border border-gray-200 hover:bg-blue-50 hover:border-blue-300 transition text-left group';

                    var iconWrap = document.createElement('div');
                    iconWrap.className = 'w-10 h-10 rounded-xl bg-rose-500/10 text-rose-500 flex items-center justify-center shrink-0';
                    var icon = document.createElement('i');
                    icon.className = 'fas fa-file-pdf text-sm';
                    iconWrap.appendChild(icon);

                    var textWrap = document.createElement('div');
                    textWrap.className = 'min-w-0 flex-1';
                    var label = document.createElement('p');
                    label.className = 'text-[10px] font-black uppercase tracking-tight text-slate-900 truncate';
                    label.textContent = entry.label || entry.name || 'PDF Document';
                    var sub = document.createElement('p');
                    sub.className = 'text-[8px] font-bold uppercase tracking-wider text-slate-500 truncate mt-0.5';
                    sub.textContent = data.kind === 'pdf' ? 'Single PDF file' : (entry.name || 'PDF document');
                    textWrap.append(label, sub);

                    var size = document.createElement('span');
                    size.className = 'text-[8px] font-black uppercase tracking-wider text-slate-400 shrink-0';
                    size.textContent = formatArchiveFileSize(entry.size);

                    var chevron = document.createElement('i');
                    chevron.className = 'fas fa-chevron-right text-[9px] text-slate-400 group-hover:text-blue-500 transition shrink-0';

                    btn.append(iconWrap, textWrap, size, chevron);
                    btn.addEventListener('click', function() { openArchivePdf(entry); });
                    list.appendChild(btn);
                });

                if (data.truncated) {
                    var note = document.createElement('p');
                    note.className = 'text-center text-[8px] font-bold uppercase tracking-widest text-amber-500 px-3 pt-2';
                    note.textContent = 'Showing the first 200 PDF files.';
                    list.appendChild(note);
                }
            })
            .catch(function(err) { archiveContentsMessage(err.message || 'Unable to load archive contents.', true); });
    }

    function openArchivePdf(entry) {
        if (!archiveContentsId) return;
        var params = new URLSearchParams({ id: String(archiveContentsId) });
        if (archiveContentsKind === 'zip' && entry.entry !== null && entry.entry !== undefined) {
            params.set('entry', String(entry.entry));
        }
        var url = '../backend/archive/view_archive_pdf.php?' + params.toString();
        var popup = window.open(url, 'cpmmisArchivePdfViewer', 'popup=yes,width=1100,height=850,resizable=yes,scrollbars=yes');
        if (!popup) {
            var link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.rel = 'noopener';
            document.body.appendChild(link);
            link.click();
            link.remove();
        } else {
            popup.focus();
        }
    }

    function closeArchiveContents() {
        var modal = document.getElementById('archive-contents-modal');
        var panel = document.getElementById('archive-contents-panel');
        if (!modal || !panel) return;
        panel.classList.add('scale-95', 'opacity-0');
        panel.classList.remove('scale-100', 'opacity-100');
        setTimeout(function() {
            modal.classList.replace('flex', 'hidden');
            var list = document.getElementById('archive-contents-list');
            if (list) list.innerHTML = '';
        }, 180);
    }
</script>
