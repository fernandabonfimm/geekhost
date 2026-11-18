</main>
<?php if (!empty($panelUser) && empty($isAuthPage)): ?>
    <footer class="panel-footer">
        <p>&copy; <?= (int) date('Y') ?> GeekHost. Todos os direitos reservados.</p>
    </footer>
</div>
<div class="panel-sidebar-backdrop" data-sidebar-close hidden></div>
<?php else: ?>
    <footer class="panel-footer panel-footer-auth">
        <p>&copy; <?= (int) date('Y') ?> GeekHost. Todos os direitos reservados.</p>
    </footer>
<?php endif; ?>

<div class="gh-toast-host" data-toast-host aria-live="polite" aria-relevant="additions"></div>

<script src="<?= e(asset('assets/js/panel.js')) ?>" defer></script>
<?php if (!empty($panelScripts)): foreach ((array) $panelScripts as $src): ?>
<script src="<?= e(asset($src)) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
