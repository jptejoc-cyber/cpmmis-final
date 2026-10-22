<?php
if (!isset($conn)) {
    include_once(__DIR__ . '/../backend/session_init.php');
    include_once(__DIR__ . '/../backend/db_config.php');
}

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'group') {
    $group_id = (int)$_SESSION['user_id'];

    $g_q = mysqli_query($conn, "SELECT g.adviser_id, a.full_name AS adviser_name, a.id AS a_id FROM `group` g LEFT JOIN adviser a ON g.adviser_id = a.id WHERE g.id = $group_id LIMIT 1");
    $g_data = ($g_q) ? mysqli_fetch_assoc($g_q) : null;
    $adv_id = ($g_data && $g_data['adviser_id'] && $g_data['a_id']) ? (int)$g_data['adviser_id'] : 0;
    $adv_name = ($g_data && $g_data['adviser_name']) ? $g_data['adviser_name'] : 'Your Adviser';

    // Fetch latest message with adviser
    $latest_msg = null;
    if ($adv_id > 0) {
        $msgs_q = mysqli_query($conn, "
            SELECT sender_id, sender_role, receiver_id, receiver_role, message, is_read
            FROM messages
            WHERE (sender_id = $group_id AND sender_role = 'group' AND receiver_id = $adv_id AND receiver_role = 'adviser')
               OR (sender_id = $adv_id AND sender_role = 'adviser' AND receiver_id = $group_id AND receiver_role = 'group')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        if ($msgs_q) $latest_msg = mysqli_fetch_assoc($msgs_q);
    }
    $has_unread = $latest_msg && $latest_msg['is_read'] == 0 && $latest_msg['sender_role'] !== 'group';
?>
    <!-- CONTACT LIST DROPDOWN -->
    <div id="group-chat-widget-window" class="hidden w-80 bg-white backdrop-blur-2xl rounded-[2.2rem] shadow-2xl border border-gray-200 z-[9999] overflow-hidden animate-scale-in flex-col">
        <div class="p-4 bg-slate-100 border-b border-gray-200 flex justify-between items-center shrink-0">
            <h4 class="text-[10px] font-black uppercase text-slate-900 tracking-widest leading-none">Messages</h4>
            <button onclick="toggleGroupChatWidget()" class="w-6 h-6 rounded-lg bg-slate-100 text-slate-500 hover:text-blue-600 transition flex items-center justify-center"><i class="fas fa-times text-[8px]"></i></button>
        </div>
        <div class="overflow-y-auto max-h-[320px] p-2 space-y-1 custom-scrollbar bg-slate-100">
            <?php if ($adv_id > 0): ?>
            <div class="px-3 py-1.5"><p class="text-[8px] font-black text-blue-400 uppercase tracking-widest">Adviser</p></div>
            <button onclick="selectGroupContact(<?php echo $adv_id; ?>, '<?php echo htmlspecialchars($adv_name, ENT_QUOTES); ?>', 'adviser')" class="group-contact-item w-full flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100 transition-all text-left border border-transparent group <?php echo $has_unread ? 'border-blue-500/20 bg-blue-500/[0.04]' : ''; ?>" data-id="<?php echo $adv_id; ?>">
                <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-400 font-black flex items-center justify-center text-[11px] group-hover:bg-blue-600 group-hover:text-white transition-all shadow-inner shrink-0">AD</div>
                <div class="flex-1 min-w-0">
                    <h4 class="text-xs font-black text-slate-700 truncate uppercase tracking-wide"><?php echo htmlspecialchars($adv_name); ?></h4>
                    <?php if ($latest_msg): ?>
                    <p class="text-[8px] <?php echo $has_unread ? 'text-slate-600 font-black' : 'text-slate-600 font-bold'; ?> truncate mt-0.5 leading-tight"><?php echo htmlspecialchars(substr($latest_msg['message'], 0, 45)); ?></p>
                    <?php endif; ?>
                </div>
                <span id="group-contact-badge" class="<?php echo $has_unread ? 'flex' : 'hidden'; ?> min-w-[16px] h-[16px] bg-blue-500 text-slate-950 font-black text-[8px] rounded-full items-center justify-center shadow-lg"><?php echo $has_unread ? '!' : '0'; ?></span>
            </button>
            <?php else: ?>
            <div class="text-center text-slate-500 text-[10px] py-8 font-bold uppercase italic tracking-wider">No adviser assigned</div>
            <?php endif; ?>


        </div>
    </div>

    <!-- BOTTOM CHAT BAR -->
    <div id="group-chat-bar" class="hidden fixed bottom-0 right-20 w-[340px] premium-card rounded-t-2xl rounded-b-none shadow-2xl border border-gray-200 border-b-0 bg-white/95 backdrop-blur-3xl flex-col overflow-hidden z-[9999] animate-slide-up">
        <div class="p-3 border-b border-gray-200 bg-slate-100 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center justify-center font-black text-[11px] shadow-md shrink-0" id="group-chat-bar-avatar">AD</div>
                <div class="min-w-0">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide truncate max-w-[200px]" id="group-chat-bar-name"><?php echo htmlspecialchars($adv_name); ?></h3>
                    <p class="text-[7px] text-slate-500 font-bold uppercase tracking-widest flex items-center gap-1">
                        <span id="group-chat-bar-status-dot" class="w-1.5 h-1.5 bg-slate-500 rounded-full"></span> <span id="group-chat-bar-status-text" class="truncate">Offline</span>
                    </p>
                </div>
            </div>
            <button onclick="toggleGroupDebug()" class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 hover:text-amber-400 transition flex items-center justify-center shrink-0" title="Chat Diagnostics"><i class="fas fa-bug text-[10px]"></i></button>
            <button onclick="closeGroupChatBar()" class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 hover:text-blue-600 transition flex items-center justify-center shrink-0"><i class="fas fa-times text-[10px]"></i></button>
        </div>
        <div id="group-chat-bar-offline-banner" class="hidden px-3 py-1.5 bg-amber-500/10 border-b border-amber-500/20 text-[8px] font-black text-amber-400 uppercase tracking-widest text-center shrink-0">
            <i class="fas fa-wifi-slash mr-1"></i> You are offline — messages will be sent when reconnected
        </div>
        <div id="group-chat-debug-panel" class="hidden border-b border-gray-200 bg-slate-100 shrink-0 overflow-hidden" style="max-height:220px;">
            <div class="p-3 space-y-1.5 custom-scrollbar overflow-y-auto" style="max-height:200px">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-[8px] font-black text-amber-400 uppercase tracking-widest"><i class="fas fa-bug mr-1"></i>Chat Diagnostics</h4>
                    <button onclick="toggleGroupDebug()" class="text-slate-500 hover:text-blue-600 text-[8px]"><i class="fas fa-times"></i></button>
                </div>
                <div id="group-debug-loading" class="text-center py-3">
                    <i class="fas fa-spinner fa-spin text-amber-400 text-[10px]"></i>
                    <p class="text-[7px] text-slate-500 mt-1 uppercase font-bold">Running diagnostics...</p>
                </div>
                <div id="group-debug-results" class="hidden space-y-1"></div>
            </div>
        </div>
        <div id="group-chat-bar-messages" class="flex-1 overflow-y-auto p-3 space-y-2 custom-scrollbar bg-slate-100" style="height:280px"></div>
        <div id="group-chat-bar-send-status" class="hidden px-3 pt-1.5 text-[8px] font-black text-rose-400 uppercase tracking-wider"></div>
        <form onsubmit="sendGroupChatBarMessage(event)" class="p-3 border-t border-gray-200 bg-slate-100 flex gap-2 items-center shrink-0">
            <input type="text" id="group-chat-bar-input" placeholder="Message..." required autocomplete="off" class="flex-1 px-3 py-2 bg-white border border-gray-200 text-slate-900 rounded-xl outline-none focus:border-blue-500 transition text-[10px] font-bold">
            <button type="submit" class="w-8 h-8 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-xl flex items-center justify-center shadow-lg hover:brightness-110 active:scale-95 transition-all shrink-0"><i class="fas fa-paper-plane text-[10px]"></i></button>
        </form>
    </div>

    <style>
        @keyframes slideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .animate-slide-up { animation: slideUp 0.2s ease-out; }
    </style>

    <script>
        (function() {
            let currentContactId = <?php echo $adv_id; ?>;
            let currentContactRole = 'adviser';
            let currentContactName = '<?php echo htmlspecialchars($adv_name, ENT_QUOTES); ?>';
            let grpWidgetOpen = false;
            let grpChatBarOpen = false;
            let grpPollInterval = null;
            let grpUnreadInterval = null;
            let grpFeedLoaded = false;
            let isFlushing = false;
            const GRP_CSRF = '<?php echo generate_csrf_token(); ?>';
            const QUEUE_KEY = 'grp_chat_offline_queue';
            const CACHE_KEY = 'grp_chat_cache';

            function escapeHtml(text) {
                const d = document.createElement('div');
                d.textContent = text;
                return d.innerHTML;
            }

            function isOnline() { return navigator.onLine; }

            function getOfflineQueue() {
                try { return JSON.parse(localStorage.getItem(QUEUE_KEY)) || []; } catch(e) { return []; }
            }
            function saveOfflineQueue(queue) {
                try { localStorage.setItem(QUEUE_KEY, JSON.stringify(queue)); } catch(e) {}
            }

            function getMsgCache(role, id) {
                try {
                    const c = JSON.parse(localStorage.getItem(CACHE_KEY)) || {};
                    return c[role + '_' + id] || [];
                } catch(e) { return []; }
            }
            function saveMsgCache(role, id, messages) {
                try {
                    const c = JSON.parse(localStorage.getItem(CACHE_KEY)) || {};
                    c[role + '_' + id] = messages;
                    localStorage.setItem(CACHE_KEY, JSON.stringify(c));
                } catch(e) {}
            }

            function renderOfflineBanner() {
                const banner = document.getElementById('group-chat-bar-offline-banner');
                if (!banner) return;
                if (!isOnline()) { banner.classList.remove('hidden'); }
                else { banner.classList.add('hidden'); }
            }

            window.toggleGroupDebug = function() {
                const panel = document.getElementById('group-chat-debug-panel');
                if (!panel) return;
                const isVisible = !panel.classList.contains('hidden');
                if (isVisible) {
                    panel.classList.add('hidden');
                    return;
                }
                panel.classList.remove('hidden');
                runGroupDiagnostics();
            };

            function runGroupDiagnostics() {
                const loading = document.getElementById('group-debug-loading');
                const results = document.getElementById('group-debug-results');
                if (loading) loading.classList.remove('hidden');
                if (results) { results.classList.add('hidden'); results.innerHTML = ''; }

                fetch('../backend/chat/chat_debug.php')
                .then(res => res.json())
                .then(data => {
                    if (loading) loading.classList.add('hidden');
                    if (!results) return;
                    results.classList.remove('hidden');

                    let html = '';
                    if (data.status === 'error') {
                        html += debugRow(false, data.message || 'Unknown error');
                    } else if (data.diagnostics) {
                        const d = data.diagnostics;
                        for (const [key, info] of Object.entries(d)) {
                            const label = key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                            html += debugRow(info.pass, label + ': ' + info.message);
                            if (info.data && typeof info.data === 'object') {
                                for (const [k, v] of Object.entries(info.data)) {
                                    if (v !== null && v !== undefined) {
                                        html += '<div class="pl-5 text-[7px] text-slate-500 font-bold">' + escapeHtml(k) + ': <span class="text-slate-500">' + escapeHtml(String(v)) + '</span></div>';
                                    }
                                }
                            }
                            if (info.count !== undefined) {
                                html += '<div class="pl-5 text-[7px] text-slate-500 font-bold">Count: <span class="text-slate-500">' + info.count + '</span></div>';
                            }
                            if (info.adviser_id !== undefined && info.adviser_id !== null) {
                                html += '<div class="pl-5 text-[7px] text-slate-500 font-bold">Adviser ID: <span class="text-slate-500">' + info.adviser_id + '</span></div>';
                            }
                        }
                    }
                    if (!html) html = '<div class="text-[7px] text-slate-500 text-center py-2">No diagnostics available</div>';
                    results.innerHTML = html;
                })
                .catch(err => {
                    if (loading) loading.classList.add('hidden');
                    if (results) {
                        results.classList.remove('hidden');
                        results.innerHTML = debugRow(false, 'Failed to run diagnostics: ' + escapeHtml(err.message));
                    }
                });
            }

            function debugRow(pass, text) {
                const icon = pass ? '<i class="fas fa-check-circle text-blue-400"></i>' : '<i class="fas fa-times-circle text-rose-400"></i>';
                const cls = pass ? 'text-slate-600' : 'text-rose-300';
                return '<div class="flex items-start gap-1.5 text-[7px] font-bold ' + cls + '">' + icon + '<span class="leading-tight">' + escapeHtml(text) + '</span></div>';
            }

            function setGroupSendStatus(text) {
                const el = document.getElementById('group-chat-bar-send-status');
                if (!el) return;
                if (text) {
                    el.textContent = text;
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                    el.textContent = '';
                }
            }

            let lastRenderedCount = 0;

            function renderMessages(messages, pendingIds) {
                const feed = document.getElementById('group-chat-bar-messages');
                if (!feed) return;

                const totalMsgs = messages.length + (pendingIds ? pendingIds.length : 0);
                const wasAtBottom = feed.scrollHeight - feed.clientHeight <= feed.scrollTop + 100;

                if (grpFeedLoaded && totalMsgs === lastRenderedCount) {
                    const pendingElements = feed.querySelectorAll('[data-pending]');
                    if (pendingIds && pendingIds.length > 0) {
                        pendingElements.forEach(el => {
                            if (!pendingIds.includes(el.getAttribute('data-pending'))) el.remove();
                        });
                    } else {
                        pendingElements.forEach(el => el.remove());
                    }
                    return;
                }

                let html = "";
                messages.forEach(msg => {
                    html += buildBubble(msg, false);
                });
                if (pendingIds && pendingIds.length > 0) {
                    pendingIds.forEach(pid => {
                        const q = getOfflineQueue().find(x => x._pendingId === pid);
                        if (q) html += buildBubble({ sender_role: 'group', message: q.message, created_at: q.created_at, _pendingId: pid }, true);
                    });
                }
                if (html === "") html = `<div class="text-center text-slate-500 text-[8px] py-10 font-bold uppercase italic tracking-wider">No messages yet. Say hello!</div>`;
                feed.innerHTML = html;
                lastRenderedCount = totalMsgs;
                grpFeedLoaded = true;
                if (wasAtBottom || feed.scrollTop === 0) feed.scrollTop = feed.scrollHeight;
            }

            function buildBubble(msg, isPending) {
                const isMe = (msg.sender_role === 'group');
                const bubbleClass = isMe ? "bg-blue-600 text-white rounded-tr-none ml-auto" : "bg-slate-100 text-slate-700 rounded-tl-none mr-auto border border-gray-200";
                const timeClass = isMe ? "text-blue-100" : "text-slate-600";
                const formatMsgDate = (dtStr) => {
                    if (!dtStr) return '';
                    const d = new Date(String(dtStr).replace(/-/g, '/'));
                    if (isNaN(d.getTime())) return dtStr;
                    const dateStr = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    const timeStr = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                    return `${dateStr}, ${timeStr}`;
                };
                const time = formatMsgDate(msg.created_at);
                const pendingDot = isPending ? ' <span class="text-amber-400" title="Sending..."><i class="fas fa-clock text-[7px]"></i></span>' : '';
                const pendingAttr = isPending ? ' data-pending="' + escapeHtml(msg._pendingId) + '"' : '';
                return `<div class="flex ${isMe ? 'justify-end' : 'justify-start'} w-full"${pendingAttr}>
                    <div class="max-w-[75%] p-3 rounded-xl shadow-md ${bubbleClass} text-[11px] font-bold leading-relaxed break-words">
                        <p>${escapeHtml(msg.message)}${pendingDot}</p>
                        <span class="block text-[9px] ${timeClass} mt-1.5 text-right uppercase font-black tracking-wider">${time}</span>
                    </div>
                </div>`;
            }

            function sendHeartbeat() {
                if (!isOnline()) return;
                fetch('../backend/chat/heartbeat.php', { method: 'POST' }).catch(() => {});
            }
            sendHeartbeat();
            setInterval(sendHeartbeat, 30000);

            function checkContactOnline() {
                if (!currentContactId || !isOnline()) return;
                fetch('../backend/chat/check_online_status.php?user_id=' + currentContactId + '&role=' + currentContactRole)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        const hDot = document.getElementById('group-chat-bar-status-dot');
                        const label = document.getElementById('group-chat-bar-status-text');
                        if (data.is_online) {
                            if (hDot) hDot.className = 'w-1.5 h-1.5 bg-blue-500 rounded-full animate-pulse';
                            if (label) label.innerText = 'Active now';
                        } else {
                            if (hDot) hDot.className = 'w-1.5 h-1.5 bg-slate-500 rounded-full';
                            if (label) label.innerText = 'Offline';
                        }
                    }
                })
                .catch(() => {});
            }
            checkContactOnline();
            setInterval(checkContactOnline, 15000);

            function updateContactHighlight(isUnread) {
                const item = document.querySelector('.group-contact-item');
                if (!item) return;
                if (isUnread) {
                    item.classList.add('border-blue-500/20', 'bg-blue-500/[0.04]');
                } else {
                    item.classList.remove('border-blue-500/20', 'bg-blue-500/[0.04]');
                }
                const preview = item.querySelector('p:last-of-type');
                if (preview) {
                    if (isUnread) {
                        preview.classList.remove('text-slate-600', 'font-bold');
                        preview.classList.add('text-slate-600', 'font-black');
                    } else {
                        preview.classList.remove('text-slate-600', 'font-black');
                        preview.classList.add('text-slate-600', 'font-bold');
                    }
                }
            }

            window.toggleGroupChatWidget = function() {
                const windowEl = document.getElementById('group-chat-widget-window');

                if (grpChatBarOpen) {
                    closeGroupChatBar();
                    openDropdown();
                    return;
                }

                grpWidgetOpen = !grpWidgetOpen;
                if (grpWidgetOpen) {
                    openDropdown();
                } else {
                    windowEl.classList.add('hidden');
                    windowEl.classList.remove('flex');
                    checkGroupUnread();
                }
            };

            function openDropdown() {
                const windowEl = document.getElementById('group-chat-widget-window');
                const btn = document.querySelector('button[onclick*="toggleGroupChatWidget"]');
                if (btn) {
                    const r = btn.getBoundingClientRect();
                    windowEl.style.position = 'fixed';
                    windowEl.style.top = (r.bottom + 8) + 'px';
                    windowEl.style.right = (window.innerWidth - r.right) + 'px';
                    windowEl.style.bottom = 'auto';
                    windowEl.style.left = 'auto';
                }
                windowEl.classList.remove('hidden');
                windowEl.classList.add('flex');

                const np = document.getElementById('notif-popover');
                if (np && !np.classList.contains('hidden')) np.classList.add('hidden');
            }

            function closeDropdown() {
                const windowEl = document.getElementById('group-chat-widget-window');
                windowEl.classList.add('hidden');
                windowEl.classList.remove('flex');
                grpWidgetOpen = false;
            }

            window.closeGroupChatBar = function() {
                const bar = document.getElementById('group-chat-bar');
                bar.classList.add('hidden');
                grpChatBarOpen = false;
                if (grpPollInterval) { clearInterval(grpPollInterval); grpPollInterval = null; }
                checkGroupUnread();
            };

            window.selectGroupContact = function(id, name, role) {
                currentContactId = id;
                currentContactName = name;
                currentContactRole = role || 'adviser';
                grpFeedLoaded = false;
                closeDropdown();

                document.getElementById('group-chat-bar-name').innerText = name;
                document.getElementById('group-chat-bar-avatar').innerText = name.substring(0, 2).toUpperCase();

                const bar = document.getElementById('group-chat-bar');
                bar.classList.remove('hidden');
                grpChatBarOpen = true;
                renderOfflineBanner();

                loadGroupChatBarMessages();
                if (grpPollInterval) clearInterval(grpPollInterval);
                grpPollInterval = setInterval(loadGroupChatBarMessages, 3000);

                const badge = document.getElementById('group-contact-badge');
                if (badge) { badge.classList.add('hidden'); badge.innerText = '0'; }

                updateContactHighlight(false);

                setTimeout(() => document.getElementById('group-chat-bar-input').focus(), 50);
                checkContactOnline();
                checkGroupUnread();
            };

            function loadGroupChatBarMessages() {
                if (!currentContactId) return;

                const queue = getOfflineQueue();
                const pendingIds = queue.filter(q => q.receiver_id == currentContactId && q.receiver_role === currentContactRole).map(q => q._pendingId);
                const pendingMsgs = pendingIds.map(pid => {
                    const q = queue.find(x => x._pendingId === pid);
                    return q ? { sender_role: 'group', message: q.message, created_at: q.created_at, _pendingId: pid } : null;
                }).filter(Boolean);

                if (!isOnline()) {
                    const cached = getMsgCache(currentContactRole, currentContactId);
                    renderMessages(cached.concat(pendingMsgs), pendingIds);
                    return;
                }

                fetch('../backend/chat/get_messages.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ other_id: currentContactId, other_role: currentContactRole, csrf_token: GRP_CSRF }) })
                .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
                .then(data => {
                    if (data.status === 'success') {
                        saveMsgCache(currentContactRole, currentContactId, data.messages);
                        renderMessages(data.messages.concat(pendingMsgs), pendingIds);
                        checkGroupUnread();
                    } else {
                        const feed = document.getElementById('group-chat-bar-messages');
                        feed.innerHTML = '<div class="text-center text-rose-400 text-[8px] py-10 font-bold uppercase italic tracking-wider">Error: ' + escapeHtml(data.message || 'unknown') + '</div>';
                    }
                })
                .catch(err => {
                    console.error("Group chat bar load failed:", err);
                    const cached = getMsgCache(currentContactRole, currentContactId);
                    if (cached.length > 0) {
                        renderMessages(cached.concat(pendingMsgs), pendingIds);
                    } else if (!grpFeedLoaded) {
                        document.getElementById('group-chat-bar-messages').innerHTML = '<div class="text-center text-rose-400 text-[8px] py-10 font-bold uppercase italic tracking-wider">Connection error</div>';
                    }
                });
            }

            function flushOfflineQueue() {
                if (isFlushing) return;
                isFlushing = true;
                const queue = getOfflineQueue();
                if (queue.length === 0) { isFlushing = false; return; }
                const remaining = [];
                let chain = Promise.resolve();
                queue.forEach((msg) => {
                    chain = chain.then(() => {
                        return fetch('../backend/chat/send_message.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                receiver_id: msg.receiver_id,
                                receiver_role: msg.receiver_role,
                                message: msg.message,
                                csrf_token: GRP_CSRF
                            })
                        })
                        .then(res => res.json().then(body => ({ ok: res.ok, body })).catch(() => { throw new Error('network'); }))
                        .then(({ ok, body }) => {
                            if (!ok || body.status !== 'success') remaining.push(msg);
                        })
                        .catch(err => {
                            if (err.name === 'TypeError') { remaining.push(msg); }
                        });
                    });
                });
                chain.then(() => {
                    saveOfflineQueue(remaining);
                    if (remaining.length < queue.length && grpChatBarOpen && currentContactId) {
                        loadGroupChatBarMessages();
                    }
                    isFlushing = false;
                }).catch(() => { isFlushing = false; });
            }

            window.sendGroupChatBarMessage = function(e) {
                e.preventDefault();
                if (!currentContactId) { console.warn('[GroupChat] No contact selected'); return; }
                const input = document.getElementById('group-chat-bar-input');
                const message = input.value.trim();
                if (message === "") return;
                input.value = "";

                const pendingId = 'p_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);
                const msgObj = {
                    receiver_id: currentContactId,
                    receiver_role: currentContactRole,
                    message: message,
                    csrf_token: GRP_CSRF,
                    created_at: new Date().toISOString(),
                    _pendingId: pendingId
                };

                console.log('[GroupChat] Sending message:', { to: currentContactId, role: currentContactRole, online: isOnline() });

                if (!isOnline()) {
                    const queue = getOfflineQueue();
                    queue.push(msgObj);
                    saveOfflineQueue(queue);
                    setGroupSendStatus('Queued — will send when online');
                    loadGroupChatBarMessages();
                    return;
                }

                fetch('../backend/chat/send_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        receiver_id: currentContactId,
                        receiver_role: currentContactRole,
                        message: message,
                        csrf_token: GRP_CSRF
                    })
                })
                .then(res => res.json().then(body => ({ ok: res.ok, status: res.status, body })).catch(() => { throw new Error('Server returned invalid response (HTTP ' + res.status + ')'); }))
                .then(({ ok, status, body }) => {
                    console.log('[GroupChat] Send response:', { ok, status, body });
                    if (!ok || body.status !== 'success') {
                        throw new Error(body.message || ('HTTP ' + status));
                    }
                    setGroupSendStatus("");
                    loadGroupChatBarMessages();
                })
                .catch(err => {
                    if (err.name === 'TypeError') {
                        const queue = getOfflineQueue();
                        queue.push(msgObj);
                        saveOfflineQueue(queue);
                        setGroupSendStatus('Queued — will send when online');
                        loadGroupChatBarMessages();
                    } else {
                        input.value = message;
                        let errDetail = err.message || 'Unknown error';
                        if (errDetail.includes('Forbidden')) errDetail += ' — Check if adviser is assigned (use bug icon)';
                        else if (errDetail.includes('CSRF') || errDetail.includes('csrf')) errDetail += ' — Refresh the page to fix';
                        else if (errDetail.includes('Invalid parameters')) errDetail += ' — Message may be empty';
                        else if (errDetail.includes('Unauthorized')) errDetail += ' — Session expired, refresh the page';
                        setGroupSendStatus('Not sent: ' + errDetail);
                        console.error('[GroupChat] Send failed:', err.message, err);
                    }
                });
            };

            function checkGroupUnread() {
                if (grpWidgetOpen || !isOnline()) return;
                fetch('../backend/chat/get_group_unread_count.php')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        const badge = document.getElementById('group-contact-badge');
                        const hasUnread = data.count > 0 && !grpChatBarOpen;
                        if (badge) {
                            if (hasUnread) { badge.innerText = data.count; badge.classList.remove('hidden'); }
                            else { badge.classList.add('hidden'); }
                        }
                        updateContactHighlight(hasUnread);

                        const msgBtn = document.querySelector('button[onclick*="toggleGroupChatWidget"]');
                        if (msgBtn) {
                            let gBadge = document.getElementById('group-msg-unread-badge');
                            if (!gBadge) {
                                gBadge = document.createElement('span');
                                gBadge.id = 'group-msg-unread-badge';
                                gBadge.className = 'absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 text-white text-[8px] font-black rounded-full border-4 border-white flex items-center justify-center';
                                gBadge.style.display = 'none';
                                msgBtn.appendChild(gBadge);
                            }
                            if (data.count > 0 && !grpChatBarOpen) { gBadge.innerText = data.count; gBadge.style.display = 'flex'; }
                            else { gBadge.style.display = 'none'; }
                        }
                    }
                })
                .catch(() => {});
            }

            window.addEventListener('online', function() {
                renderOfflineBanner();
                flushOfflineQueue();
                if (grpChatBarOpen && currentContactId) {
                    loadGroupChatBarMessages();
                    checkContactOnline();
                }
                checkGroupUnread();
            });
            window.addEventListener('offline', function() {
                renderOfflineBanner();
                const hDot = document.getElementById('group-chat-bar-status-dot');
                const label = document.getElementById('group-chat-bar-status-text');
                if (hDot) hDot.className = 'w-1.5 h-1.5 bg-slate-500 rounded-full';
                if (label) label.innerText = 'Offline';
            });

            checkGroupUnread();
            grpUnreadInterval = setInterval(checkGroupUnread, 5000);
            flushOfflineQueue();

            document.addEventListener('click', function(e) {
                if (grpChatBarOpen) {
                    const notifBtn = document.querySelector('button[onclick*="handleNotifClick"]');
                    const settingsBtn = document.querySelector('button[onclick*="toggleSettingsDropdown"]');
                    if ((notifBtn && notifBtn.contains(e.target)) || (settingsBtn && settingsBtn.contains(e.target))) {
                        closeGroupChatBar();
                        return;
                    }
                }
                const windowEl = document.getElementById('group-chat-widget-window');
                const btn = document.querySelector('button[onclick*="toggleGroupChatWidget"]');
                if (grpWidgetOpen && windowEl && !windowEl.contains(e.target) && (!btn || !btn.contains(e.target))) {
                    toggleGroupChatWidget();
                }
            });
        })();
    </script>
<?php } ?>
