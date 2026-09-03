<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Industrias Carpicenter — Iniciar Sesión</title>
    <meta name="description" content="Sistema de gestión empresarial de Industrias Carpicenter.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css?v=5.0">
</head>
<body class="login-body">

    <div class="login-split">

        <!-- ===== LADO IZQUIERDO: Imagen ===== -->
        <div class="split-left">
            <div class="split-overlay"></div>
            <div class="left-content">
                <img src="assets/img/logo_carpicenter_darkmode.png" alt="Carpicenter Logo" class="brand-logo-large">
                <h3 class="brand-slogan">MUEBLES QUE TRANSFORMAN ESPACIOS</h3>
            </div>
            <div class="split-copyright">© 2024 Carpicenter Industrias. Todos los derechos reservados.</div>
        </div>

        <!-- ===== LADO DERECHO: Formulario ===== -->
        <div class="split-right">
            <div class="pattern-dots"></div>
            <div class="glow-bg"></div>
            
            <div class="login-card">

                <!-- Cabecera -->
                <div class="card-header">
                    <h2>Bienvenido de vuelta</h2>
                    <p>Inicia sesión para continuar con tu gestión</p>
                </div>

                <!-- Formulario -->
                <form action="auth/login_process.php" method="POST" id="loginForm" autocomplete="off">

                    <div class="form-group">
                        <label for="login-user">Usuario o correo</label>
                        <div class="input-wrapper">
                            <input type="text" id="login-user" name="user"
                                placeholder="Ingrese su usuario o correo" required autocomplete="username">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="login-pass">Contraseña</label>
                        <div class="input-wrapper">
                            <input type="password" id="login-pass" name="pass"
                                placeholder="Ingrese su contraseña" required autocomplete="current-password">
                            <button type="button" class="btn-eye" id="togglePass" aria-label="Ver contraseña">
                                <i class="far fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-container">
                            <input type="checkbox" id="remember" name="remember" checked>
                            <span class="checkmark"><i class="fas fa-check"></i></span>
                            RECORDARME
                        </label>
                        <a href="auth/recuperar.php" class="forgot-link">¿Olvidaste tu contraseña?</a>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span class="btn-text">INICIAR SESIÓN</span>
                        <i class="fas fa-arrow-right btn-arrow"></i>
                        <div class="btn-loader" style="display:none;">
                            <i class="fas fa-circle-notch fa-spin"></i>
                        </div>
                    </button>

                </form>

                <!-- Separador -->
                <div class="divider"><span>o continúa con</span></div>

                <!-- Botones sociales -->
                <div class="social-login" style="justify-content: center;">
                    <button class="btn-social btn-fingerprint" type="button" id="btnBiometric">
                        <i class="fas fa-fingerprint" style="font-size: 1.5rem; color: #333;"></i>
                    </button>
                </div>

                <!-- Pie -->
                <div class="card-footer">
                    <p>¿No tienes una cuenta? <a href="auth/recuperar.php">Contactar al administrador</a></p>
                    <p class="copyright">© 2026 Industrias <strong>Carpicenter</strong>. Todos los derechos reservados.</p>
                </div>

            </div>
        </div>

    </div>

    <!-- MODAL: Error de Acceso / Credenciales Inválidas (Fondo Blanco Elegante) -->
    <div class="modal-overlay" id="loginErrorModal" style="position:fixed; inset:0; background:rgba(15,23,42,0.7); backdrop-filter: blur(6px); z-index:2050; display:none; align-items:center; justify-content:center; padding:1rem;">
        <div class="modal-box" style="max-width:390px; width:100%; text-align:center; background:#FFFFFF; border-radius:20px; border:1px solid #E2E8F0; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.25); padding:2rem 1.8rem;">
            <div style="width:64px; height:64px; border-radius:50%; background:#FEE2E2; border:1px solid #FECACA; display:flex; align-items:center; justify-content:center; margin:0 auto 1.2rem; color:#DC2626; font-size:1.6rem; box-shadow:0 10px 20px rgba(220,38,38,0.12);">
                <i class="fas fa-lock"></i>
            </div>
            <h3 style="margin:0 0 0.4rem; font-size:1.25rem; font-weight:800; color:#0F172A; font-family:'Inter',sans-serif;">Credenciales Incorrectas</h3>
            <p style="color:#64748B; font-size:0.88rem; line-height:1.5; margin:0 0 1.5rem; font-family:'Inter',sans-serif;" id="loginErrorModalMsg">
                El usuario o la contraseña ingresada no son válidos. Por favor, verifica tus datos e inténtalo nuevamente.
            </p>
            <div style="display:flex; flex-direction:column; gap:0.6rem;">
                <button type="button" onclick="closeLoginErrorModal()" style="width:100%; padding:0.8rem; border-radius:10px; border:none; background:#E31E24; color:#FFFFFF; cursor:pointer; font-weight:700; font-size:0.9rem; transition:all 0.2s; box-shadow:0 4px 12px rgba(227,30,36,0.25);">
                    <i class="fas fa-rotate-right" style="margin-right:6px;"></i> Intentar Nuevamente
                </button>
                <a href="auth/recuperar.php" style="width:100%; text-decoration:none; text-align:center; box-sizing:border-box; padding:0.75rem; border-radius:10px; border:1px solid #CBD5E1; background:#F8FAFC; color:#334155; font-weight:600; font-size:0.85rem; transition:all 0.2s; display:inline-block;">
                    <i class="fas fa-key" style="margin-right:6px; color:#E31E24;"></i> ¿Olvidaste tu contraseña?
                </a>
            </div>
        </div>
    </div>

    <script src="assets/js/login.js?v=<?= time() ?>"></script>
    <?php if (isset($_GET['error'])): ?>
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            const err = '<?= htmlspecialchars($_GET['error']) ?>';
            const msg = (err === 'inactive_user') 
                ? 'Esta cuenta se encuentra inactiva en el sistema. Contacta al administrador.' 
                : 'Usuario o contraseña incorrectos. Por favor, verifica tus datos.';
            openLoginErrorModal(msg);
        });
    </script>
    <?php endif; ?>
</body>
</html>