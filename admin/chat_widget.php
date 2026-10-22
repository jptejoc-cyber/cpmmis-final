<?php
if (!isset($conn)) {
    include_once(__DIR__ . '/../backend/session_init.php');
    include_once(__DIR__ . '/../backend/db_config.php');
}

if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    $admin_id = (int)$_SESSION['user_id'];

    // Fetch latest message per adviser contact
    $latest_msgs = [];
    $msgs_q = mysqli_query($conn, "
        SELECT sender_id, sender_role, receiver_id, receiver_role, message, is_read
        FROM messages
        WHERE (sender_id = $admin_id AND sender_role = 'admin')
           OR (receiver_id = $admin_id AND receiver_role = 'admin')
        ORDER BY created_at DESC
        LIMIT 500
    ");
    if ($msgs_q) {
        while ($m = mysqli_fetch_assoc($msgs_q)) {
            $other_id = ($m['sender_id'] == $admin_id && $m['sender_role'] == 'admin') ? $m['receiver_id'] : $m['sender_id'];
            $other_role = ($m['sender_role'] == 'admin') ? $m['receiver_role'] : $m['sender_role'];
            $key = $other_role . '_' . $other_id;
            if (!isset($latest_msgs[$key])) {
                $latest_msgs[$key] = $m;
            }
        }
    }
?>
    <!-- CONTACT LIST DROPDOWN (like notif-popover) -->
    <div id="admin-chat-widget-window" class="hidden w-80 bg-white backdrop-blur-2xl rounded-[2.2rem] shadow-2xl border border-gray-200 z-[9999] overflow-hidden animate-scale-in flex-col">
        <div class="p-4 bg-slate-100 border-b border-gray-200 flex justify-between items-center shrink-0">
            <h4 class="text-[10px] font-black uppercase text-slate-900 tracking-widest leading-none">Messages</h4>
            <button onclick="toggleAdminChatWidget()" class="w-6 h-6 rounded-lg bg-slate-100 text-slate-500 hover:text-blue-600 transition flex items-center justify-center"><i class="fas fa-times text-[8px]"></i></button>
        </div>
        <div class="overflow-y-auto max-h-[320px] p-2 space-y-1 custom-scrollbar bg-slate-100">
            <div class="px-3 py-1.5"><p class="text-[8px] font-black text-blue-400 uppercase tracking-widest">Advisers</p></div>
            <?php
            $advs_q = mysqli_query($conn, "SELECT id, username, full_name FROM adviser ORDER BY full_name ASC LIMIT 100");
            if ($advs_q && mysqli_num_rows($advs_q) > 0):
                while ($a = mysqli_fetch_assoc($advs_q)):
                    $initials = strtoupper(substr($a['full_name'], 0, 2));
                    $a_key = 'adviser_' . $a['id'];
                    $a_last = $latest_msgs[$a_key] ?? null;
                    $a_unread = $a_last && $a_last['is_read'] == 0 && $a_last['sender_role'] !== 'admin';
            ?>
            <button onclick="selectAdminContact('adviser', <?php echo $a['id']; ?>, '<?php echo htmlspecialchars($a['full_name'], ENT_QUOTES); ?>')" class="admin-contact-item w-full flex items-center gap-3 p-3 rounded-xl hover:bg-slate-100 transition-all text-left border border-transparent group <?php echo $a_unread ? 'border-blue-500/20 bg-blue-500/[0.04]' : ''; ?>" data-role="adviser" data-id="<?php echo $a['id']; ?>">
                <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-400 font-black flex items-center justify-center text-[11px] group-hover:bg-blue-600 group-hover:text-white transition-all shadow-inner shrink-0"><?php echo $initials; ?></div>
                <div class="flex-1 min-w-0">
                    <h4 class="text-xs font-black text-slate-700 truncate uppercase tracking-wide"><?php echo htmlspecialchars($a['full_name']); ?></h4>
                    <?php if ($a_last): ?>
                    <p class="text-[8px] <?php echo $a_unread ? 'text-slate-600 font-black' : 'text-slate-600 font-bold'; ?> truncate mt-0.5 leading-tight"><?php echo htmlspecialchars(substr($a_last['message'], 0, 45)); ?></p>
                    <?php endif; ?>
                </div>
                <span id="admin-contact-badge-adviser_<?php echo $a['id']; ?>" class="<?php echo $a_unread ? 'flex' : 'hidden'; ?> min-w-[16px] h-[16px] bg-blue-500 text-slate-950 font-black text-[8px] rounded-full items-center justify-center shadow-lg"><?php echo $a_unread ? '!' : '0'; ?></span>
            </button>
            <?php endwhile; else: ?>
            <div class="text-center text-slate-500 text-[8px] py-4 font-bold uppercase italic tracking-wider">No advisers found</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- BOTTOM CHAT BAR (Facebook-style) -->
    <div id="admin-chat-bar" class="hidden fixed bottom-0 right-20 w-[340px] premium-card rounded-t-2xl rounded-b-none shadow-2xl border border-gray-200 border-b-0 bg-white/95 backdrop-blur-3xl flex-col overflow-hidden z-[9999] animate-slide-up">
        <div class="p-3 border-b border-gray-200 bg-slate-100 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-400 border border-blue-500/20 flex items-center justify-center font-black text-[11px] shadow-md shrink-0" id="admin-chat-bar-avatar">--</div>
                <div class="min-w-0">
                    <h3 class="text-xs font-black text-slate-900 uppercase tracking-wide truncate max-w-[200px]" id="admin-chat-bar-name">--</h3>
                    <p class="text-[7px] text-slate-500 font-bold uppercase tracking-widest flex items-center gap-1">
                        <span id="admin-chat-bar-status-dot" class="w-1.5 h-1.5 bg-slate-500 rounded-full"></span> <span id="admin-chat-bar-status-text" class="truncate">Offline</span>
                    </p>
                </div>
            </div>
            <button onclick="closeAdminChatBar()" class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 hover:text-blue-600 transition flex items-center justify-center shrink-0"><i class="fas fa-times text-[10px]"></i></button>
        </div>
        <div id="admin-chat-bar-offline-banner" class="hidden px-3 py-1.5 bg-amber-500/10 border-b border-amber-500/20 text-[8px] font-black text-amber-400 uppercase tracking-widest text-center shrink-0">
            <i class="fas fa-wifi-slash mr-1"></i> You are offline — messages will be sent when reconnected
        </div>
        <div id="admin-chat-bar-messages" class="flex-1 overflow-y-auto p-3 space-y-2 custom-scrollbar bg-slate-100" style="height:280px"></div>
        <div id="admin-chat-bar-send-status" class="hidden px-3 pt-1.5 text-[8px] font-black text-rose-400 uppercase tracking-wider"></div>
        <form onsubmit="sendAdminChatBarMessage(event)" class="p-3 border-t border-gray-200 bg-slate-100 flex gap-2 items-center shrink-0">
            <input type="text" id="admin-chat-bar-input" placeholder="Type a message..." required autocomplete="off" class="flex-1 px-3 py-2 bg-white border border-gray-200 text-slate-900 rounded-xl outline-none focus:border-blue-500 transition text-[10px] font-bold">
            <button type="submit" class="w-8 h-8 bg-gradient-to-r from-blue-600 to-blue-500 text-white rounded-xl flex items-center justify-center shadow-lg hover:brightness-110 active:scale-95 transition-all shrink-0"><i class="fas fa-paper-plane text-[10px]"></i></button>
        </form>
    </div>

    <style>
        @keyframes slideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .animate-slide-up { animation: slideUp 0.2s ease-out; }
    </style>

    <script>
        (function() {
            let advWidgetOpen = false;
            let advChatBarOpen = false;
            let advActiveRole = null;
            let advActiveId = null;
            let advActiveName = "";
            let advPollInterval = null;
            let advUnreadInterval = null;
            let advFeedLoaded = false;
            let isFlushing = false;
            const ADMIN_CSRF = '<?php echo generate_csrf_token(); ?>';
            const QUEUE_KEY = 'admin_chat_offline_queue';
            const CACHE_KEY = 'admin_chat_cache';

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
                const banner = document.getElementById('admin-chat-bar-offline-banner');
                if (!banner) return;
                if (!isOnline()) { banner.classList.remove('hidden'); }
                else { banner.classList.add('hidden'); }
            }

            function setAdminSendStatus(text) {
                const el = document.getElementById('admin-chat-bar-send-status');
                if (!el) return;
                if (text) {
                    el.textContent = text;
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                    el.textContent = '';
                }
            }

            function renderMessages(messages, pendingIds) {
                const feed = document.getElementById('admin-chat-bar-messages');
                if (!feed) return;
                const wasAtBottom = feed.scrollHeight - feed.clientHeight <= feed.scrollTop + 100;
                let html = "";
                messages.forEach(msg => {
                    const isMe = (msg.sender_role === 'admin');
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
                    const isPending = pendingIds && pendingIds.includes(msg._pendingId);
                    const pendingDot = isPending ? ' <span class="text-amber-400" title="Sending..."><i class="fas fa-clock text-[7px]"></i></span>' : '';
                    html += `<div class="flex ${isMe ? 'justify-end' : 'justify-start'} w-full animate-scale-in">
                        <div class="max-w-[75%] p-3 rounded-xl shadow-md ${bubbleClass} text-[11px] font-bold leading-relaxed break-words">
                            <p>${escapeHtml(msg.message)}${pendingDot}</p>
                            <span class="block text-[9px] ${timeClass} mt-1.5 text-right uppercase font-black tracking-wider">${time}</span>
                        </div>
                    </div>`;
                });
                if (html === "") html = `<div class="text-center text-slate-500 text-[8px] py-10 font-bold uppercase italic tracking-wider">No messages yet.</div>`;
                feed.innerHTML = html;
                advFeedLoaded = true;
                if (wasAtBottom || feed.scrollTop === 0) feed.scrollTop = feed.scrollHeight;
            }

            function sendHeartbeat() {
                if (!isOnline()) return;
                fetch('../backend/chat/heartbeat.php', { method: 'POST' }).catch(() => {});
            }
            sendHeartbeat();
            setInterval(sendHeartbeat, 30000);

            function checkContactOnline() {
                if (!advActiveId || !isOnline()) return;
                fetch('../backend/chat/check_online_status.php?user_id=' + advActiveId + '&role=' + advActiveRole)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        const hDot = document.getElementById('admin-chat-bar-status-dot');
                        const label = document.getElementById('admin-chat-bar-status-text');
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

            function updateContactHighlight(role, id, isUnread) {
                const item = document.querySelector(`.admin-contact-item[data-role="${role}"][data-id="${id}"]`);
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

            window.toggleAdminChatWidget = function() {
                const windowEl = document.getElementById('admin-chat-widget-window');

                if (advChatBarOpen) {
                    closeAdminChatBar();
                    openDropdown();
                    return;
                }

                advWidgetOpen = !advWidgetOpen;

                if (advWidgetOpen) {
                    openDropdown();
                } else {
                    windowEl.classList.add('hidden');
                    windowEl.classList.remove('flex');
                    advActiveRole = null;
                    advActiveId = null;
                    advActiveName = "";
                    pollAdminUnread();
                }
            };

            function openDropdown() {
                const windowEl = document.getElementById('admin-chat-widget-window');
                const btn = document.querySelector('button[onclick*="toggleAdminChatWidget"]');
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
                const windowEl = document.getElementById('admin-chat-widget-window');
                windowEl.classList.add('hidden');
                windowEl.classList.remove('flex');
                advWidgetOpen = false;
            }

            window.closeAdminChatBar = function() {
                const bar = document.getElementById('admin-chat-bar');
                bar.classList.add('hidden');
                advChatBarOpen = false;
                if (advPollInterval) { clearInterval(advPollInterval); advPollInterval = null; }
                advActiveRole = null;
                advActiveId = null;
                advActiveName = "";
                pollAdminUnread();
            };

            window.selectAdminContact = function(role, id, name) {
                advActiveRole = role;
                advActiveId = id;
                advActiveName = name;
                advFeedLoaded = false;

                closeDropdown();

                document.getElementById('admin-chat-bar-name').innerText = name;
                document.getElementById('admin-chat-bar-avatar').innerText = name.substring(0, 2).toUpperCase();

                const bar = document.getElementById('admin-chat-bar');
                bar.classList.remove('hidden');
                advChatBarOpen = true;
                renderOfflineBanner();

                loadAdminChatBarMessages();
                if (advPollInterval) clearInterval(advPollInterval);
                advPollInterval = setInterval(loadAdminChatBarMessages, 3000);

                const badge = document.getElementById('admin-contact-badge-' + role + '_' + id);
                if (badge) { badge.classList.add('hidden'); badge.innerText = '0'; }

                updateContactHighlight(role, id, false);

                setTimeout(() => document.getElementById('admin-chat-bar-input').focus(), 50);
                checkContactOnline();
                pollAdminUnread();
            };

            function loadAdminChatBarMessages() {
                if (!advActiveId || !advActiveRole) return;

                const queue = getOfflineQueue();
                const pendingIds = queue.filter(q => q.receiver_id == advActiveId && q.receiver_role === advActiveRole).map(q => q._pendingId);
                const pendingMsgs = pendingIds.map(pid => {
                    const q = queue.find(x => x._pendingId === pid);
                    return q ? { sender_role: 'admin', message: q.message, created_at: q.created_at, _pendingId: pid } : null;
                }).filter(Boolean);

                if (!isOnline()) {
                    const cached = getMsgCache(advActiveRole, advActiveId);
                    renderMessages(cached.concat(pendingMsgs), pendingIds);
                    return;
                }

                fetch(`../backend/chat/get_messages.php`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ other_id: advActiveId, other_role: advActiveRole, csrf_token: ADMIN_CSRF }) })
                .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
                .then(data => {
                    if (!advActiveId) return;
                    if (data.status === 'success') {
                        saveMsgCache(advActiveRole, advActiveId, data.messages);
                        renderMessages(data.messages.concat(pendingMsgs), pendingIds);
                        pollAdminUnread();
                    } else {
                        const feed = document.getElementById('admin-chat-bar-messages');
                        feed.innerHTML = '<div class="text-center text-rose-400 text-[8px] py-10 font-bold uppercase italic tracking-wider">Error: ' + escapeHtml(data.message || 'unknown') + '</div>';
                    }
                })
                .catch(err => {
                    console.error("Admin chat bar load failed:", err);
                    const cached = getMsgCache(advActiveRole, advActiveId);
                    if (cached.length > 0) {
                        renderMessages(cached.concat(pendingMsgs), pendingIds);
                    } else if (!advFeedLoaded) {
                        document.getElementById('admin-chat-bar-messages').innerHTML = '<div class="text-center text-rose-400 text-[8px] py-10 font-bold uppercase italic tracking-wider">Connection error</div>';
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
                                csrf_token: ADMIN_CSRF
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
                    if (remaining.length < queue.length && advChatBarOpen && advActiveId) {
                        loadAdminChatBarMessages();
                    }
                    isFlushing = false;
                }).catch(() => { isFlushing = false; });
            }

            window.sendAdminChatBarMessage = function(e) {
                e.preventDefault();
                if (!advActiveId || !advActiveRole) return;
                const input = document.getElementById('admin-chat-bar-input');
                const message = input.value.trim();
                if (message === "") return;
                input.value = "";

                const pendingId = 'p_' + Date.now() + '_' + Math.random().toString(36).substring(2, 8);
                const msgObj = {
                    receiver_id: advActiveId,
                    receiver_role: advActiveRole,
                    message: message,
                    csrf_token: ADMIN_CSRF,
                    created_at: new Date().toISOString(),
                    _pendingId: pendingId
                };

                if (!isOnline()) {
                    const queue = getOfflineQueue();
                    queue.push(msgObj);
                    saveOfflineQueue(queue);
                    setAdminSendStatus('Queued — will send when online');
                    loadAdminChatBarMessages();
                    return;
                }

                fetch('../backend/chat/send_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        receiver_id: advActiveId,
                        receiver_role: advActiveRole,
                        message: message,
                        csrf_token: ADMIN_CSRF
                    })
                })
                .then(res => res.json().then(body => ({ ok: res.ok, status: res.status, body })).catch(() => { throw new Error('Server returned invalid response (HTTP ' + res.status + ')'); }))
                .then(({ ok, status, body }) => {
                    if (!ok || body.status !== 'success') {
                        throw new Error(body.message || ('HTTP ' + status));
                    }
                    setAdminSendStatus("");
                    loadAdminChatBarMessages();
                })
                .catch(err => {
                    if (err.name === 'TypeError') {
                        const queue = getOfflineQueue();
                        queue.push(msgObj);
                        saveOfflineQueue(queue);
                        setAdminSendStatus('Queued — will send when online');
                        loadAdminChatBarMessages();
                    } else {
                        input.value = message;
                        setAdminSendStatus('Not sent: ' + err.message);
                    }
                });
            };

            function pollAdminUnread() {
                if (advWidgetOpen || !isOnline()) return;
                fetch('../backend/chat/get_unread_chat_counts.php')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        let totalUnread = 0;
                        const counts = data.counts || {};

                        document.querySelectorAll('.admin-contact-item').forEach(item => {
                            const role = item.dataset.role;
                            const id = item.dataset.id;
                            const key = role + '_' + id;
                            const count = counts[key] || 0;
                            const badge = document.getElementById('admin-contact-badge-' + key);
                            if (badge) {
                                if (count > 0 && (parseInt(id) !== advActiveId || role !== advActiveRole)) {
                                    badge.innerText = count;
                                    badge.style.display = 'flex';
                                    totalUnread += count;
                                } else {
                                    badge.style.display = 'none';
                                }
                            }
                            updateContactHighlight(role, id, count > 0 && (parseInt(id) !== advActiveId || role !== advActiveRole));
                        });

                        const msgBtn = document.querySelector('button[onclick*="toggleAdminChatWidget"]');
                        if (msgBtn) {
                            let badge = document.getElementById('admin-msg-unread-badge');
                            if (!badge) {
                                badge = document.createElement('span');
                                badge.id = 'admin-msg-unread-badge';
                                badge.className = 'absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 text-white text-[8px] font-black rounded-full border-4 border-white flex items-center justify-center';
                                badge.style.display = 'none';
                                msgBtn.appendChild(badge);
                            }
                            if (totalUnread > 0) { badge.innerText = totalUnread; badge.style.display = 'flex'; }
                            else { badge.style.display = 'none'; }
                        }
                    }
                })
                .catch(err => console.error("Admin unread poll error:", err));
            }

            window.addEventListener('online', function() {
                renderOfflineBanner();
                flushOfflineQueue();
                if (advChatBarOpen && advActiveId) {
                    loadAdminChatBarMessages();
                    checkContactOnline();
                }
                pollAdminUnread();
            });
            window.addEventListener('offline', function() {
                renderOfflineBanner();
                const hDot = document.getElementById('admin-chat-bar-status-dot');
                const label = document.getElementById('admin-chat-bar-status-text');
                if (hDot) hDot.className = 'w-1.5 h-1.5 bg-slate-500 rounded-full';
                if (label) label.innerText = 'Offline';
            });

            pollAdminUnread();
            advUnreadInterval = setInterval(pollAdminUnread, 5000);
            flushOfflineQueue();

            document.addEventListener('click', function(e) {
                if (advChatBarOpen) {
                    const notifBtn = document.querySelector('button[onclick*="handleNotifClick"]');
                    const settingsBtn = document.querySelector('button[onclick*="toggleSettingsDropdown"]');
                    if ((notifBtn && notifBtn.contains(e.target)) || (settingsBtn && settingsBtn.contains(e.target))) {
                        closeAdminChatBar();
                        return;
                    }
                }
                const windowEl = document.getElementById('admin-chat-widget-window');
                const btn = document.querySelector('button[onclick*="toggleAdminChatWidget"]');
                if (advWidgetOpen && windowEl && !windowEl.contains(e.target) && (!btn || !btn.contains(e.target))) {
                    toggleAdminChatWidget();
                }
            });
        })();
    </script>
<?php } ?>
