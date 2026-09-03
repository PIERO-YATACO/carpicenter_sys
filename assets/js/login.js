// ============================================================
//  LOGIN.JS — Carpicenter ERP (Modern & Robust)
// ============================================================

// Global Modal Controls (available immediately in window scope)
window.openForgotModal = function() {
    const modal = document.getElementById('forgotPassModal');
    if (modal) {
        modal.style.display = 'flex';
        const userTyped = document.getElementById('login-user')?.value || '';
        const identInput = document.getElementById('forgotIdentifier');
        if (identInput) {
            if (userTyped) identInput.value = userTyped;
            identInput.focus();
        }
    }
};

window.closeForgotModal = function() {
    const modal = document.getElementById('forgotPassModal');
    if (modal) modal.style.display = 'none';
};

window.openLoginErrorModal = function(message) {
    const modal = document.getElementById('loginErrorModal');
    const msgEl = document.getElementById('loginErrorModalMsg');
    if (modal) {
        if (msgEl && message) msgEl.textContent = message;
        modal.style.display = 'flex';
    }
};

window.closeLoginErrorModal = function() {
    const modal = document.getElementById('loginErrorModal');
    if (modal) modal.style.display = 'none';
    const passInput = document.getElementById('login-pass');
    if (passInput) {
        passInput.value = '';
        passInput.focus();
    }
};

document.addEventListener('DOMContentLoaded', () => {

    // ── Toggle mostrar/ocultar contraseña ──
    const toggleBtn = document.getElementById('togglePass');
    const passInput = document.getElementById('login-pass');
    const eyeIcon   = document.getElementById('eyeIcon');

    if (toggleBtn && passInput) {
        toggleBtn.addEventListener('click', () => {
            const isPassword = passInput.type === 'password';
            passInput.type   = isPassword ? 'text' : 'password';
            eyeIcon.className = isPassword ? 'fas fa-eye' : 'fas fa-eye-slash';
        });
    }

    // ── Manejo Asíncrono de Login (Sin pantallas blancas) ──
    const form      = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');

    if (form && submitBtn) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const userInput = document.getElementById('login-user');
            const passInput = document.getElementById('login-pass');

            const userVal = (userInput?.value || '').trim();
            const passVal = (passInput?.value || '').trim();

            if (!userVal || !passVal) {
                window.openLoginErrorModal('Por favor, ingresa tu usuario y contraseña.');
                return;
            }

            // Mostrar el loader en el botón
            const btnText   = submitBtn.querySelector('.btn-text');
            const btnArrow  = submitBtn.querySelector('.btn-arrow');
            const btnLoader = submitBtn.querySelector('.btn-loader');

            if (btnText)   btnText.style.display   = 'none';
            if (btnArrow)  btnArrow.style.display  = 'none';
            if (btnLoader) btnLoader.style.display = 'inline-flex';
            submitBtn.disabled = true;

            try {
                const formData = new FormData(form);
                formData.append('ajax', '1');

                const response = await fetch('auth/login_process.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (data.success && data.redirect) {
                    // Éxito: Redirigir al dashboard
                    window.location.href = data.redirect;
                } else {
                    // Error: Mostrar modal elegante
                    window.openLoginErrorModal(data.message || 'Usuario o contraseña incorrectos.');
                    
                    // Resetear estado del botón
                    if (btnText)   btnText.style.display   = 'inline';
                    if (btnArrow)  btnArrow.style.display  = 'inline';
                    if (btnLoader) btnLoader.style.display = 'none';
                    submitBtn.disabled = false;
                }
            } catch (err) {
                console.error('Error login:', err);
                window.openLoginErrorModal('Ocurrió un problema de conexión al validar credenciales. Por favor, reintenta.');
                if (btnText)   btnText.style.display   = 'inline';
                if (btnArrow)  btnArrow.style.display  = 'inline';
                if (btnLoader) btnLoader.style.display = 'none';
                submitBtn.disabled = false;
            }
        });
    }

    // ── Botones sociales ──
    document.getElementById('btnBiometric')?.addEventListener('click', () => {
        window.openForgotModal();
    });

    // ── Recuperar Contraseña Modal Logic ──
    const forgotModal = document.getElementById('forgotPassModal');
    const btnVerifyUser = document.getElementById('btnVerifyUser');
    const btnConfirmReset = document.getElementById('btnConfirmReset');
    const btnBackToStep1 = document.getElementById('btnBackToStep1');
    const btnGoLogin = document.getElementById('btnGoLogin');

    const forgotStep1 = document.getElementById('forgotStep1');
    const forgotStep2 = document.getElementById('forgotStep2');
    const forgotStep3 = document.getElementById('forgotStep3');

    const forgotIdentifier = document.getElementById('forgotIdentifier');
    const forgotNewPass = document.getElementById('forgotNewPass');
    const forgotMasterPin = document.getElementById('forgotMasterPin');

    const forgotStep1Error = document.getElementById('forgotStep1Error');
    const forgotStep1ErrorText = document.getElementById('forgotStep1ErrorText');
    const forgotStep2Error = document.getElementById('forgotStep2Error');
    const forgotStep2ErrorText = document.getElementById('forgotStep2ErrorText');

    let currentVerifiedUserId = null;
    let currentVerifiedUsername = null;

    if (forgotModal) {
        forgotModal.addEventListener('click', (e) => {
            if (e.target === forgotModal) window.closeForgotModal();
        });

        btnBackToStep1?.addEventListener('click', () => {
            showStep(1);
        });

        btnGoLogin?.addEventListener('click', () => {
            window.closeForgotModal();
            const loginUserInput = document.getElementById('login-user');
            const loginPassInput = document.getElementById('login-pass');
            if (loginUserInput && currentVerifiedUsername) {
                loginUserInput.value = currentVerifiedUsername;
            }
            if (loginPassInput) {
                loginPassInput.value = '';
                loginPassInput.focus();
            }
        });

        // Verificar Usuario / Correo
        btnVerifyUser?.addEventListener('click', async () => {
            const ident = (forgotIdentifier?.value || '').trim();
            if (!ident) {
                showError(1, 'Por favor, ingrese su usuario o correo.');
                return;
            }

            hideError(1);
            btnVerifyUser.disabled = true;
            btnVerifyUser.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';

            try {
                const formData = new FormData();
                formData.append('action', 'verify');
                formData.append('identifier', ident);

                const res = await fetch('auth/recuperar_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    currentVerifiedUserId = data.user_id;
                    currentVerifiedUsername = data.username;

                    // Llenar vista de paso 2
                    document.getElementById('userVerifiedName').textContent = data.nombre;
                    document.getElementById('userVerifiedUsername').textContent = '@' + data.username;
                    document.getElementById('userVerifiedRole').textContent = data.rol;
                    document.getElementById('userAvatarLetter').textContent = data.nombre.charAt(0).toUpperCase();

                    // Configurar enlace de WhatsApp directo
                    const wsBtn = document.getElementById('btnWhatsAppAdmin');
                    if (wsBtn) {
                        const msg = `Hola Administración Carpicenter, soy el usuario *${data.nombre}* (@${data.username}). Olvidé mi contraseña de acceso al sistema y solicito restablecer mis credenciales.`;
                        wsBtn.href = `https://wa.me/51927961032?text=${encodeURIComponent(msg)}`;
                    }

                    showStep(2);
                } else {
                    showError(1, data.message || 'No se encontró la cuenta.');
                }
            } catch (err) {
                showError(1, 'Ocurrió un error al contactar al servidor.');
            } finally {
                btnVerifyUser.disabled = false;
                btnVerifyUser.innerHTML = '<span>Verificar Cuenta</span> <i class="fas fa-arrow-right"></i>';
            }
        });

        // Confirmar Restablecimiento
        btnConfirmReset?.addEventListener('click', async () => {
            const newPass = (forgotNewPass?.value || '').trim();
            const pin = (forgotMasterPin?.value || '').trim();

            if (!newPass) {
                showError(2, 'Por favor, ingrese su nueva contraseña.');
                return;
            }
            if (newPass.length < 4) {
                showError(2, 'La contraseña debe tener mínimo 4 caracteres.');
                return;
            }

            hideError(2);
            btnConfirmReset.disabled = true;
            btnConfirmReset.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';

            try {
                const formData = new FormData();
                formData.append('action', 'reset_password');
                formData.append('user_id', currentVerifiedUserId);
                formData.append('new_password', newPass);
                formData.append('master_pin', pin);

                const res = await fetch('auth/recuperar_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    showStep(3);
                } else {
                    showError(2, data.message || 'No se pudo actualizar la contraseña.');
                }
            } catch (err) {
                showError(2, 'Ocurrió un error en la conexión.');
            } finally {
                btnConfirmReset.disabled = false;
                btnConfirmReset.innerHTML = '<i class="fas fa-check-circle"></i> Restablecer Contraseña';
            }
        });
    }

    function showStep(step) {
        if (forgotStep1) forgotStep1.style.display = (step === 1) ? 'block' : 'none';
        if (forgotStep2) forgotStep2.style.display = (step === 2) ? 'block' : 'none';
        if (forgotStep3) forgotStep3.style.display = (step === 3) ? 'block' : 'none';
        hideError(1);
        hideError(2);
    }

    function showError(step, msg) {
        if (step === 1 && forgotStep1Error && forgotStep1ErrorText) {
            forgotStep1ErrorText.textContent = msg;
            forgotStep1Error.style.display = 'block';
        } else if (step === 2 && forgotStep2Error && forgotStep2ErrorText) {
            forgotStep2ErrorText.textContent = msg;
            forgotStep2Error.style.display = 'block';
        }
    }

    function hideError(step) {
        if (step === 1 && forgotStep1Error) forgotStep1Error.style.display = 'none';
        if (step === 2 && forgotStep2Error) forgotStep2Error.style.display = 'none';
    }

});
