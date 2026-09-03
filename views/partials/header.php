<?php $page_title = $page_title ?? 'Dashboard'; $page_subtitle = $page_subtitle ?? ''; ?>
<script src="/carpicenter_sys/assets/js/theme.js"></script>

<header class="topbar">
    <div class="topbar-left">
        <button class="btn-icon sidebar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('open')" style="display:none;">
            <i class="fas fa-bars"></i>
        </button>
        <div>
            <h1><?= htmlspecialchars($page_title) ?></h1>
            <?php if($page_subtitle): ?><span class="breadcrumb-text"><?= htmlspecialchars($page_subtitle) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="topbar-search">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Buscar en el sistema...">
    </div>
    <div class="topbar-right">
        <div class="topbar-icon">
            <i class="fas fa-bell"></i>
            <span class="badge-dot"></span>
        </div>
        <a href="/carpicenter_sys/views/perfil.php" class="topbar-user" style="text-decoration:none; color:inherit;">
            <?php if(!empty($_SESSION['foto_url'])): ?>
                <img src="<?= htmlspecialchars($_SESSION['foto_url']) ?>" alt="Avatar" class="topbar-avatar" style="object-fit: cover; border: 1px solid var(--border-color);">
            <?php else: ?>
                <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'A', 0, 1)) ?></div>
            <?php endif; ?>
            <span><?= htmlspecialchars($_SESSION['nombre_completo'] ?? $_SESSION['username'] ?? 'Administrador') ?></span>
        </a>
    </div>
</header>
