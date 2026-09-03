<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña — Industrias Carpicenter</title>
    <meta name="description" content="Recuperación de contraseña y acceso al sistema Carpicenter.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        body {
            background-color: #0F172A;
            background-image: 
                radial-gradient(at 100% 0%, rgba(227, 30, 36, 0.15) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(37, 99, 235, 0.12) 0px, transparent 50%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            color: #0F172A;
        }

        .recovery-container {
            width: 100%;
            max-width: 460px;
        }

        .brand-header {
            text-align: center;
            margin-bottom: 1.8rem;
        }

        .brand-header img {
            height: 48px;
            width: auto;
            object-fit: contain;
            margin-bottom: 0.8rem;
        }

        .recovery-card {
            background: #FFFFFF;
            border-radius: 20px;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.35);
            border: 1px solid #E2E8F0;
            overflow: hidden;
        }

        .card-top-bar {
            padding: 1.6rem 2rem 1.2rem;
            border-bottom: 1px solid #F1F5F9;
            background: #FAFAFA;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-top-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #FEE2E2;
            border: 1px solid #FECACA;
            color: #DC2626;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .card-top-titles h1 {
            font-size: 1.15rem;
            font-weight: 800;
            color: #0F172A;
            margin-bottom: 2px;
        }

        .card-top-titles p {
            font-size: 0.78rem;
            color: #64748B;
            font-weight: 500;
        }

        .card-content {
            padding: 1.8rem 2rem;
        }

        .form-desc {
            font-size: 0.88rem;
            color: #475569;
            line-height: 1.5;
            margin-bottom: 1.4rem;
        }

        .input-group {
            margin-bottom: 1.3rem;
        }

        .input-group label {
            display: block;
            font-size: 0.74rem;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper input {
            width: 100%;
            background: #F8FAFC;
            border: 1.5px solid #CBD5E1;
            border-radius: 10px;
            padding: 11px 14px 11px 40px;
            color: #0F172A;
            font-size: 0.92rem;
            outline: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .input-wrapper input:focus {
            border-color: #E31E24;
            background: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(227, 30, 36, 0.12);
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94A3B8;
            font-size: 0.9rem;
        }

        .btn-primary-red {
            width: 100%;
            background: #E31E24;
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 12px;
            font-weight: 700;
            font-size: 0.92rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(227, 30, 36, 0.25);
            text-decoration: none;
        }

        .btn-primary-red:hover {
            background: #C6181E;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(227, 30, 36, 0.35);
        }

        .btn-whatsapp {
            width: 100%;
            background: #16A34A;
            color: #FFFFFF;
            border: none;
            border-radius: 10px;
            padding: 11px;
            font-weight: 700;
            font-size: 0.86rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.2);
            text-decoration: none;
            text-align: center;
        }

        .btn-whatsapp:hover {
            background: #15803D;
            transform: translateY(-1px);
            color: #FFFFFF;
        }

        .btn-ghost {
            background: transparent;
            border: none;
            color: #64748B;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            padding: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 4px;
        }

        .btn-ghost:hover {
            color: #0F172A;
        }

        .user-card-preview {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.4rem;
        }

        .user-avatar-circle {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #2563EB;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .alert-box-error {
            padding: 10px 14px;
            border-radius: 8px;
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #DC2626;
            font-size: 0.82rem;
            margin-bottom: 1.2rem;
            font-weight: 600;
            display: none;
        }

        .footer-nav {
            text-align: center;
            margin-top: 1.5rem;
        }

        .footer-nav a {
            color: #94A3B8;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .footer-nav a:hover {
            color: #FFFFFF;
        }
    </style>
</head>
<body>

    <div class="recovery-container">

        <!-- Logo Superior -->
        <div class="brand-header">
            <a href="../index.php">
                <img src="../assets/img/logo_carpicenter_darkmode.png" alt="Carpicenter">
            </a>
        </div>

        <!-- Tarjeta de Recuperación -->
        <div class="recovery-card">
            
            <!-- Cabecera -->
            <div class="card-top-bar">
                <div class="card-top-icon">
                    <i class="fas fa-key"></i>
                </div>
                <div class="card-top-titles">
                    <h1>Recuperar Contraseña</h1>
                    <p>Acceso seguro al sistema Carpicenter</p>
                </div>
            </div>

            <!-- Contenido -->
            <div class="card-content">
                
                <!-- PASO 1: Identificación -->
                <div id="step1">
                    <p class="form-desc">
                        Ingresa tu <strong>nombre de usuario</strong> o tu <strong>correo registrado</strong> para validar tu cuenta en el sistema.
                    </p>

                    <div class="input-group">
                        <label for="identifierInput">Usuario o Correo</label>
                        <div class="input-wrapper">
                            <input type="text" id="identifierInput" placeholder="Ingresa tu usuario o correo electrónico" autocomplete="username">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>

                    <div id="errorStep1" class="alert-box-error">
                        <i class="fas fa-circle-exclamation" style="margin-right:6px;"></i> <span id="errorStep1Msg"></span>
                    </div>

                    <button type="button" id="btnVerify" class="btn-primary-red">
                        <span>Verificar Cuenta</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>

                <!-- PASO 2: Restablecimiento -->
                <div id="step2" style="display:none;">
                    
                    <div class="user-card-preview">
                        <div class="user-avatar-circle" id="userAvatar">U</div>
                        <div style="flex:1; overflow:hidden;">
                            <h4 style="margin:0; font-size:0.94rem; font-weight:800; color:#0F172A; white-space:nowrap; text-overflow:ellipsis; overflow:hidden;" id="userName">Nombre</h4>
                            <div style="display:flex; align-items:center; gap:6px; margin-top:3px;">
                                <span style="font-size:0.75rem; color:#64748B;" id="userHandle">@usuario</span>
                                <span style="font-size:0.68rem; background:#EFF6FF; color:#1D4ED8; border:1px solid #BFDBFE; padding:1px 6px; border-radius:4px; font-weight:700;" id="userRole">Rol</span>
                            </div>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="newPassInput">Nueva Contraseña</label>
                        <div class="input-wrapper" style="margin-bottom:10px;">
                            <input type="password" id="newPassInput" placeholder="Escribe tu nueva contraseña..." autocomplete="new-password">
                            <i class="fas fa-lock"></i>
                        </div>

                        <label for="masterPinInput">Código de Autorización / PIN Maestro</label>
                        <div class="input-wrapper">
                            <input type="text" id="masterPinInput" placeholder="Código o PIN de autorización...">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <small style="color:#64748B; font-size:0.72rem; display:block; margin-top:5px;">* Si no cuentas con el código, puedes solicitarlo por WhatsApp a soporte.</small>
                    </div>

                    <div id="errorStep2" class="alert-box-error">
                        <i class="fas fa-circle-exclamation" style="margin-right:6px;"></i> <span id="errorStep2Msg"></span>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <button type="button" id="btnResetPass" class="btn-primary-red">
                            <i class="fas fa-check-circle"></i> Restablecer Contraseña
                        </button>

                        <a id="btnWhatsAppSupport" href="#" target="_blank" class="btn-whatsapp">
                            <i class="fab fa-whatsapp" style="font-size:1.1rem;"></i> Solicitar Clave por WhatsApp a Soporte
                        </a>

                        <div style="text-align:center;">
                            <button type="button" id="btnBackTo1" class="btn-ghost">
                                <i class="fas fa-arrow-left"></i> Probar con otro usuario
                            </button>
                        </div>
                    </div>

                </div>

                <!-- PASO 3: Éxito -->
                <div id="step3" style="display:none; text-align:center; padding:1rem 0;">
                    <div style="width:68px; height:68px; border-radius:50%; background:#DCFCE7; border:1px solid #BBF7D0; color:#16A34A; display:flex; align-items:center; justify-content:center; margin:0 auto 1.2rem; font-size:2rem; box-shadow:0 10px 20px rgba(22,163,74,0.15);">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 style="margin:0 0 6px; font-size:1.25rem; color:#0F172A; font-weight:800;">¡Contraseña Restablecida!</h3>
                    <p style="color:#64748B; font-size:0.88rem; line-height:1.5; margin:0 0 1.6rem;">
                        Tu nueva contraseña ha sido guardada correctamente. Ya puedes ingresar al sistema con tus nuevas credenciales.
                    </p>
                    <a href="../index.php" class="btn-whatsapp" style="background:#16A34A; padding:12px; font-size:0.92rem;">
                        Ir a Iniciar Sesión
                    </a>
                </div>

            </div>

        </div>

        <!-- Enlace Volver -->
        <div class="footer-nav">
            <a href="../index.php">
                <i class="fas fa-arrow-left"></i> Volver a la pantalla de inicio de sesión
            </a>
        </div>

    </div>

    <script>
        let verifiedUserId = null;
        let verifiedUsername = null;

        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');
        const step3 = document.getElementById('step3');

        const identInput = document.getElementById('identifierInput');
        const newPassInput = document.getElementById('newPassInput');
        const masterPinInput = document.getElementById('masterPinInput');

        const errorStep1 = document.getElementById('errorStep1');
        const errorStep1Msg = document.getElementById('errorStep1Msg');
        const errorStep2 = document.getElementById('errorStep2');
        const errorStep2Msg = document.getElementById('errorStep2Msg');

        const btnVerify = document.getElementById('btnVerify');
        const btnResetPass = document.getElementById('btnResetPass');
        const btnBackTo1 = document.getElementById('btnBackTo1');

        identInput.focus();

        identInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') btnVerify.click();
        });

        newPassInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') btnResetPass.click();
        });

        btnBackTo1.addEventListener('click', () => {
            step1.style.display = 'block';
            step2.style.display = 'none';
            step3.style.display = 'none';
            errorStep1.style.display = 'none';
            errorStep2.style.display = 'none';
            identInput.focus();
        });

        // Verificar Usuario / Correo
        btnVerify.addEventListener('click', async () => {
            const ident = identInput.value.trim();
            if (!ident) {
                errorStep1Msg.textContent = 'Por favor, ingresa tu usuario o correo electrónico.';
                errorStep1.style.display = 'block';
                return;
            }

            errorStep1.style.display = 'none';
            btnVerify.disabled = true;
            btnVerify.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';

            try {
                const formData = new FormData();
                formData.append('action', 'verify');
                formData.append('identifier', ident);

                const res = await fetch('recuperar_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    verifiedUserId = data.user_id;
                    verifiedUsername = data.username;

                    document.getElementById('userName').textContent = data.nombre;
                    document.getElementById('userHandle').textContent = '@' + data.username;
                    document.getElementById('userRole').textContent = data.rol;
                    document.getElementById('userAvatar').textContent = data.nombre.charAt(0).toUpperCase();

                    const msg = `Hola Administración Carpicenter, soy el usuario *${data.nombre}* (@${data.username}). Olvidé mi contraseña de acceso al sistema y solicito restablecer mis credenciales.`;
                    document.getElementById('btnWhatsAppSupport').href = `https://wa.me/51927961032?text=${encodeURIComponent(msg)}`;

                    step1.style.display = 'none';
                    step2.style.display = 'block';
                    newPassInput.focus();
                } else {
                    errorStep1Msg.textContent = data.message || 'No se encontró la cuenta.';
                    errorStep1.style.display = 'block';
                }
            } catch (err) {
                errorStep1Msg.textContent = 'Ocurrió un error al contactar al servidor.';
                errorStep1.style.display = 'block';
            } finally {
                btnVerify.disabled = false;
                btnVerify.innerHTML = '<span>Verificar Cuenta</span> <i class="fas fa-arrow-right"></i>';
            }
        });

        // Confirmar Restablecimiento
        btnResetPass.addEventListener('click', async () => {
            const newPass = newPassInput.value.trim();
            const pin = masterPinInput.value.trim();

            if (!newPass) {
                errorStep2Msg.textContent = 'Por favor, ingresa tu nueva contraseña.';
                errorStep2.style.display = 'block';
                return;
            }
            if (newPass.length < 4) {
                errorStep2Msg.textContent = 'La contraseña debe tener mínimo 4 caracteres.';
                errorStep2.style.display = 'block';
                return;
            }

            errorStep2.style.display = 'none';
            btnResetPass.disabled = true;
            btnResetPass.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';

            try {
                const formData = new FormData();
                formData.append('action', 'reset_password');
                formData.append('user_id', verifiedUserId);
                formData.append('new_password', newPass);
                formData.append('master_pin', pin);

                const res = await fetch('recuperar_ajax.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    step2.style.display = 'none';
                    step3.style.display = 'block';
                } else {
                    errorStep2Msg.textContent = data.message || 'No se pudo actualizar la contraseña.';
                    errorStep2.style.display = 'block';
                }
            } catch (err) {
                errorStep2Msg.textContent = 'Ocurrió un error en la conexión.';
                errorStep2.style.display = 'block';
            } finally {
                btnResetPass.disabled = false;
                btnResetPass.innerHTML = '<i class="fas fa-check-circle"></i> Restablecer Contraseña';
            }
        });
    </script>
</body>
</html>
