<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="/carpicenter_sys/assets/js/app.js"></script>

<!-- MODAL: Confirmar Cierre de Sesión -->
<div class="modal-overlay" id="confirmLogoutModal" style="position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:2000;display:none;align-items:center;justify-content:center;padding:1rem;">
    <div class="modal-box" style="max-width:400px; text-align: center; background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border-color); overflow: hidden;">
        <div class="modal-body" style="padding: 2.5rem 2rem;">
            <div style="width: 70px; height: 70px; border-radius: 50%; background: rgba(198, 40, 40, 0.12); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto;">
                <i class="fas fa-sign-out-alt" style="font-size: 2rem; color: var(--primary-light);"></i>
            </div>
            <h3 style="margin-bottom: 0.6rem; font-size: 1.4rem; font-weight: 700; color: var(--text-primary);">¿Cerrar Sesión?</h3>
            <p style="color: var(--text-secondary); font-size: 0.92rem; margin-bottom: 2rem; line-height: 1.5;">¿Estás seguro de que deseas salir del sistema Carpicenter? Tendrás que ingresar tus credenciales nuevamente.</p>
            <div style="display: flex; gap: 0.8rem; justify-content: center;">
                <button onclick="closeLogoutModal()" class="btn btn-outline" style="flex: 1; justify-content: center; padding: 0.7rem;">Cancelar</button>
                <a href="/carpicenter_sys/auth/logout.php" class="btn btn-primary" style="flex: 1; justify-content: center; padding: 0.7rem; background: var(--primary);"><i class="fas fa-sign-out-alt"></i> Salir</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmLogout(e) {
    if (e) e.preventDefault();
    document.getElementById('confirmLogoutModal').style.display = 'flex';
}
function closeLogoutModal() {
    document.getElementById('confirmLogoutModal').style.display = 'none';
}
document.getElementById('confirmLogoutModal').addEventListener('click', function(e) {
    if (e.target === this) closeLogoutModal();
});
</script>

<!-- CARPIBOT - ASISTENTE VIRTUAL INTERNO -->
<link rel="stylesheet" href="/carpicenter_sys/assets/css/chatbot.css?v=<?= time() ?>">
<script src="/carpicenter_sys/assets/js/chatbot.js?v=<?= time() ?>"></script>

