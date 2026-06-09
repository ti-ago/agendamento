<header style="display:flex; align-items:center; justify-content:space-between; padding:10px 24px; background:#fff; border-bottom:1px solid #dcdcdc; flex-shrink:0;">
    <div style="display:flex; align-items:center; gap:10px;">
        <img src="images/logo.jpg" alt="Facilite" height="48" style="height:48px;">
    </div>
    <?php if (isset($_SESSION) && isset($_SESSION['id'])): ?>
        <div style="font-size:0.8rem; color:#888;">
            <?= htmlspecialchars($_SESSION['nome'] ?? '') ?>
            <a href="logout.php" style="margin-left:12px; color:#3465a4; text-decoration:none;">Sair</a>
        </div>
    <?php endif; ?>
</header>
