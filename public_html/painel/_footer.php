</main>
<?php if (!empty($panelUser)): ?>
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
<?php if (!empty($companyWhatsapp)): ?>
    <a class="whatsapp-float" href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp GeekHost">
        <i class="bi bi-whatsapp" aria-hidden="true"></i>
    </a>
<?php endif; ?>
<button class="scroll-top" type="button" data-scroll-top aria-label="Voltar ao topo" hidden><span aria-hidden="true">↑</span></button>
<script src="<?= e(asset('assets/js/site.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/panel.js')) ?>" defer></script>
<?php if (!empty($panelScripts)): foreach ((array) $panelScripts as $src): ?>
<script src="<?= e(asset($src)) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
