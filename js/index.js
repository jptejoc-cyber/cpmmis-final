(function () {
    'use strict';

    let cfg = { csrf_token: '', total_groups: 0, total_advisers: 0, total_projects: 0 };
    (function () {
        const el = document.getElementById('page-config');
        if (el) {
            try { cfg = JSON.parse(el.textContent || '{}'); } catch (e) { cfg = {}; }
        }
    })();

    function showToast(msg, type) {
        if (type === undefined) type = 'success';
        const t = document.getElementById('toast');
        const bg = document.getElementById('toast-bg');
        const iconBg = document.getElementById('toast-icon-bg');
        const icon = document.getElementById('toast-icon');
        const msgEl = document.getElementById('toast-msg');
        if (!t || !msgEl) return;

        msgEl.innerText = msg;

        if (type === 'error' || type === 'pending') {
            bg.style.border = '1px solid rgba(239,68,68,0.25)';
            iconBg.style.background = 'rgba(239,68,68,0.8)';
            icon.innerHTML = '<path fill="currentColor" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM216 336h24v80c0 8.8-7.2 16-16 16h-8c-8.8 0-16-7.2-16-16v-80h-8c-8.8 0-16-7.2-16-16s7.2-16 16-16h64c8.8 0 16 7.2 16 16s-7.2 16-16 16zm-8-112c-8.8 0-16-7.2-16-16V208c0-8.8 7.2-16 16-16s16 7.2 16 16v96c0 8.8-7.2 16-16 16z"/>';
        } else {
            bg.style.border = '1px solid rgba(24,119,242,0.25)';
            iconBg.style.background = 'rgba(24,119,242,0.8)';
            icon.innerHTML = '<path fill="currentColor" d="M256 512A256 256 0 1 0 256 0a256 256 0 1 0 0 512zM369 209L241 337c-9.4 9.4-24.6 9.4-33.9 0l-64-64c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0l47 47L335 175c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9z"/>';
        }

        t.classList.add('show');
        setTimeout(function () { t.classList.remove('show'); }, 4000);
    }

    function toggleModal(id) {
        const m = document.getElementById(id);
        if (m) {
            m.classList.toggle('active');
        }
    }

    const SVG_EYE = '<svg viewBox="0 0 640 512" width="14" height="14"><path fill="currentColor" d="M320 400c-75.85 0-146.5-41.11-201.6-108.7C47.37 290.4 16.85 244.1 4.57 216.1c-1.183-26.6 7.522-49.49 22.75-65.1C40.37 135.7 100.2 96 176 96c38.6 0 73.8 13.8 101 37l13 10.6c7.5 6.2 18.2 6.2 25.7 0l13-10.6C363.2 109.8 398.4 96 437 96c75.8 0 135.6 39.7 149.7 55c15.2 15.6 24.3 38.5 22.75 65.1C601.2 236.4 570.6 282.7 521.6 291.3C466.5 358.9 395.8 400 320 400zM49.7 216.1C61.4 235.4 85.7 268 125.4 298.6 177.1 339.4 247 368 320 368s142.9-28.6 194.6-69.4c39.7-30.6 64-63.2 75.7-82.5C596.2 206.4 598.5 182.7 592 160c-7.7-27.5-30.4-60.6-84.8-87.4C477.7 46.8 418.9 32 352 32c-66.9 0-125.7 14.8-155.2 40.6C142.4 99.4 119.7 132.5 112 160c-6.5 22.7-4.2 46.4 7.7 56.1zM320 288c-35.3 0-64-28.7-64-64s28.7-64 64-64 64 28.7 64 64-28.7 64-64 64zm0-96c-17.6 0-32 14.4-32 32s14.4 32 32 32 32-14.4 32-32-14.4-32-32-32z"/></svg>';
    const SVG_EYE_SLASH = '<svg viewBox="0 0 640 512" width="14" height="14"><path fill="currentColor" d="M320 400c-75.85 0-146.5-41.11-201.6-108.7C47.37 290.4 16.85 244.1 4.57 216.1c-1.183-26.6 7.522-49.49 22.75-65.1C40.37 135.7 100.2 96 176 96c38.6 0 73.8 13.8 101 37l13 10.6c7.5 6.2 18.2 6.2 25.7 0l13-10.6C363.2 109.8 398.4 96 437 96c75.8 0 135.6 39.7 149.7 55c15.2 15.6 24.3 38.5 22.75 65.1C601.2 236.4 570.6 282.7 521.6 291.3C466.5 358.9 395.8 400 320 400zM49.7 216.1C61.4 235.4 85.7 268 125.4 298.6 177.1 339.4 247 368 320 368s142.9-28.6 194.6-69.4c39.7-30.6 64-63.2 75.7-82.5C596.2 206.4 598.5 182.7 592 160c-7.7-27.5-30.4-60.6-84.8-87.4C477.7 46.8 418.9 32 352 32c-66.9 0-125.7 14.8-155.2 40.6C142.4 99.4 119.7 132.5 112 160c-6.5 22.7-4.2 46.4 7.7 56.1zM320 288c-35.3 0-64-28.7-64-64s28.7-64 64-64 64 28.7 64 64-28.7 64-64 64zm0-96c-17.6 0-32 14.4-32 32s14.4 32 32 32 32-14.4 32-32-14.4-32-32-32z"/><line x1="48" y1="48" x2="592" y2="464" stroke="currentColor" stroke-width="40"/></svg>';

    function toggleVisibility(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (!input || !icon) return;
        if (input.type === 'password') {
            input.type = 'text';
            icon.outerHTML = SVG_EYE.replace('<svg', '<svg id="' + iconId + '"');
        } else {
            input.type = 'password';
            icon.outerHTML = SVG_EYE_SLASH.replace('<svg', '<svg id="' + iconId + '"');
        }
    }

    function spinnerSvg() {
        return '<svg viewBox="0 0 512 512" width="12" height="12" style="animation:spin 1s linear infinite;margin-right:0.5rem;vertical-align:middle"><path fill="currentColor" d="M304 48c0-26.5-21.5-48-48-48s-48 21.5-48 48v48H144c-26.5 0-48 21.5-48 48v320c0 26.5 21.5 48 48 48h224c26.5 0 48-21.5 48-48V96c0-26.5-21.5-48-48-48h-64zM192 128c26.5 0 48 21.5 48 48s-21.5 48-48 48-48-21.5-48-48 21.5-48 48-48z"/></svg>';
    }

    function parseJsonResponse(res) {
        const ct = res.headers.get('content-type') || '';
        if (!res.ok) {
            return res.text().then(function (body) {
                let msg = 'Server error (HTTP ' + res.status + '). Please try again.';
                if (body && /^[\s]*\{/.test(body)) {
                    try {
                        const j = JSON.parse(body);
                        if (j && j.message) msg = j.message;
                    } catch (e) { /* ignore */ }
                }
                return { status: 'error', message: msg };
            });
        }
        if (ct.indexOf('application/json') === -1) {
            return res.text().then(function (body) {
                const snippet = (body || '').replace(/\s+/g, ' ').slice(0, 120);
                return { status: 'error', message: snippet ? ('Unexpected response: ' + snippet) : 'Unexpected empty response from the server.' };
            });
        }
        return res.json().then(function (data) {
            return (data && typeof data === 'object') ? data : { status: 'error', message: 'Unexpected server response.' };
        });
    }

    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = spinnerSvg() + ' Verifying...';

            fetch('backend/auth/login_process.php', { method: 'POST', body: new FormData(this) })
            .then(parseJsonResponse)
            .then(function (data) {
                if (data.status === 'success') {
                    showToast('Access Granted. Redirecting...', 'success');
                    setTimeout(function () {
                        const dest = data.redirect || (data.role + '/' + data.role + '_dashboard.php');
                        window.location.href = dest;
                    }, 1000);
                } else if (data.status === 'pending') {
                    btn.disabled = false;
                    btn.innerHTML = 'Sign In';
                    showToast(data.message, 'pending');
                } else {
                    btn.disabled = false;
                    btn.innerHTML = 'Sign In';
                    showToast(data.message || 'The user or password you entered is incorrect.', 'error');
                }
            })
            .catch(function (err) {
                btn.disabled = false;
                btn.innerHTML = 'Sign In';
                showToast('Backend Connection Failed. Check your network and try again.', 'error');
            });
        });
    }

    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = document.getElementById('regBtn');
            btn.disabled = true;
            btn.innerHTML = spinnerSvg() + ' Submitting...';

            fetch('backend/groups/register_group.php', { method: 'POST', body: new FormData(this) })
            .then(parseJsonResponse)
            .then(function (data) {
                btn.disabled = false;
                btn.innerHTML = 'Register Account';
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    this.reset();
                    setTimeout(function () { toggleModal('signup-modal'); }, 1500);
                } else {
                    showToast(data.message || 'Registration failed.', 'error');
                }
            }.bind(this))
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = 'Register Account';
                showToast('Connection to registration endpoint failed.', 'error');
            });
        });
    }

    let resendTimerId = null;
    let otpCooldownTimerId = null;

    function startOtpCooldown(seconds) {
        const box = document.getElementById('otp-cooldown');
        const secEl = document.getElementById('otp-cooldown-seconds');
        const btn = document.getElementById('verifyOtpBtn');
        if (!box || !btn || !secEl) return;
        clearInterval(otpCooldownTimerId);
        let left = parseInt(seconds, 10) > 0 ? parseInt(seconds, 10) : 60;
        btn.disabled = true;
        btn.style.opacity = '0.5';
        box.style.display = 'block';
        secEl.innerText = left;
        otpCooldownTimerId = setInterval(function () {
            left--;
            if (left <= 0) {
                clearInterval(otpCooldownTimerId);
                btn.disabled = false;
                btn.style.opacity = '1';
                box.style.display = 'none';
            } else {
                secEl.innerText = left;
            }
        }, 1000);
    }

    function startResendCooldown(seconds) {
        const wrap = document.getElementById('resend-wrap');
        const btn = document.getElementById('resendOtpBtn');
        const secEl = document.getElementById('resend-seconds');
        if (!wrap || !btn || !secEl) return;
        clearInterval(resendTimerId);
        let left = parseInt(seconds, 10) > 0 ? parseInt(seconds, 10) : 60;
        btn.style.display = 'none';
        wrap.style.display = 'inline';
        secEl.innerText = left;
        resendTimerId = setInterval(function () {
            left--;
            if (left <= 0) {
                clearInterval(resendTimerId);
                wrap.style.display = 'none';
                btn.style.display = 'inline';
            } else {
                secEl.innerText = left;
            }
        }, 1000);
    }

    function resetForgotFlow() {
        clearInterval(resendTimerId);
        clearInterval(otpCooldownTimerId);
        document.getElementById('step-1').style.display = 'block';
        document.getElementById('step-2').style.display = 'none';
        document.getElementById('step-3').style.display = 'none';
        document.getElementById('resend-wrap').style.display = 'inline';
        document.getElementById('resendOtpBtn').style.display = 'none';
        const verifyBtn = document.getElementById('verifyOtpBtn');
        verifyBtn.disabled = false;
        verifyBtn.style.opacity = '1';
        document.getElementById('otp-cooldown').style.display = 'none';
        document.getElementById('forgot-desc').innerText = 'Enter your registered email address to receive an OTP.';
    }

    function sendOtp(isResend) {
        const email = document.getElementById('forgot_email').value;
        if (!email) { showToast('Please enter your email address.', 'error'); return; }

        const btn = isResend ? document.getElementById('resendOtpBtn') : document.getElementById('sendOtpBtn');
        const originalLabel = isResend ? 'Resend Code' : 'Send Recovery Code';
        btn.disabled = true;
        btn.innerHTML = 'Sending...';

        const formData = new FormData();
        formData.append('email', email);
        formData.append('csrf_token', cfg.csrf_token);

        fetch('backend/auth/send_otp.php', { method: 'POST', body: formData })
        .then(function (res) { return res.text(); })
        .then(function (text) {
            btn.disabled = false;
            btn.innerHTML = originalLabel;
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Raw server response:', text);
                showToast('Mail service error. Please try again later.', 'error');
                return;
            }
            if (data.status === 'success') {
                showToast(data.message, 'success');
                document.getElementById('step-1').style.display = 'none';
                document.getElementById('step-2').style.display = 'block';
                document.getElementById('forgot-desc').innerText = 'Enter the 6-digit code sent to your email.';
                startResendCooldown(data.cooldown || 60);
            } else {
                showToast(data.message, 'error');
                if (data.retry_after > 0) {
                    startResendCooldown(data.retry_after);
                } else if (isResend) {
                    startResendCooldown(60);
                }
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = originalLabel;
            showToast('Connection failed.', 'error');
        });
    }

    const sendOtpBtn = document.getElementById('sendOtpBtn');
    if (sendOtpBtn) sendOtpBtn.addEventListener('click', function () { sendOtp(false); });
    const resendOtpBtn = document.getElementById('resendOtpBtn');
    if (resendOtpBtn) resendOtpBtn.addEventListener('click', function () { sendOtp(true); });

    function verifyOtp() {
        const email = document.getElementById('forgot_email').value;
        const otp = document.getElementById('forgot_otp').value;
        if (!otp) { showToast('Please enter the OTP.', 'error'); return; }

        const btn = document.getElementById('verifyOtpBtn');
        btn.disabled = true;
        btn.innerHTML = spinnerSvg() + ' Verifying...';

        const formData = new FormData();
        formData.append('email', email);
        formData.append('otp', otp);
        formData.append('csrf_token', cfg.csrf_token);

        fetch('backend/auth/verify_otp.php', { method: 'POST', body: formData })
        .then(parseJsonResponse)
        .then(function (data) {
            btn.disabled = false;
            btn.innerHTML = 'Verify Code';
            if (data.status === 'success') {
                showToast(data.message, 'success');
                document.getElementById('step-2').style.display = 'none';
                document.getElementById('step-3').style.display = 'block';
                document.getElementById('forgot-desc').innerText = 'Create and confirm your new password.';
            } else {
                showToast(data.message, 'error');
                if (data.retry_after > 0) {
                    startOtpCooldown(data.retry_after);
                }
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = 'Verify Code';
            showToast('Connection failed.', 'error');
        });
    }

    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    if (verifyOtpBtn) verifyOtpBtn.addEventListener('click', verifyOtp);

    function resetPasswordFinal() {
        const email = document.getElementById('forgot_email').value;
        const otp = document.getElementById('forgot_otp').value;
        const new_pass = document.getElementById('forgot_new_pass').value;
        const conf_pass = document.getElementById('forgot_conf_pass').value;

        if (!new_pass || !conf_pass) { showToast('Please fill all password fields.', 'error'); return; }
        if (new_pass !== conf_pass) { showToast('Passwords do not match.', 'error'); return; }

        const btn = document.getElementById('resetPassBtn');
        btn.disabled = true;
        btn.innerHTML = spinnerSvg() + ' Saving...';

        const formData = new FormData();
        formData.append('email', email);
        formData.append('otp', otp);
        formData.append('new_password', new_pass);
        formData.append('csrf_token', cfg.csrf_token);

        fetch('backend/auth/reset_password_final.php', { method: 'POST', body: formData })
        .then(parseJsonResponse)
        .then(function (data) {
            btn.disabled = false;
            btn.innerHTML = 'Save New Password';
            if (data.status === 'success') {
                showToast(data.message, 'success');
                document.getElementById('forgotForm').reset();
                setTimeout(function () {
                    toggleModal('forgot-modal');
                    resetForgotFlow();
                }, 2000);
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(function () {
            btn.disabled = false;
            btn.innerHTML = 'Save New Password';
            showToast('Connection failed.', 'error');
        });
    }

    const resetPassBtn = document.getElementById('resetPassBtn');
    if (resetPassBtn) resetPassBtn.addEventListener('click', resetPasswordFinal);

    function animateCounter(el, target) {
        let current = 0;
        const step = Math.ceil(target / 60);
        const interval = setInterval(function () {
            current += step;
            if (current >= target) {
                current = target;
                clearInterval(interval);
            }
            el.textContent = current;
        }, 25);
    }

    document.addEventListener('click', function (e) {
        let t = e.target;
        while (t && t !== document) {
            if (t.hasAttribute && t.hasAttribute('data-toggle-modal')) {
                toggleModal(t.getAttribute('data-toggle-modal'));
                return;
            }
            if (t.hasAttribute && t.hasAttribute('data-toggle-vis')) {
                const pair = t.getAttribute('data-toggle-vis').split('|');
                toggleVisibility(pair[0], pair[1]);
                return;
            }
            t = t.parentNode;
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        animateCounter(document.getElementById('statGroups'), cfg.total_groups);
        animateCounter(document.getElementById('statAdvisers'), cfg.total_advisers);
        animateCounter(document.getElementById('statProjects'), cfg.total_projects);
    });
})();
