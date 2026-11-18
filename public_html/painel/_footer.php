</main>
<footer class="panel-footer">
    <div class="page-width panel-footer-inner">
        <p>GeekHost · Área do cliente · sessão expira em 7 horas</p>
        <a href="/">Voltar ao site</a>
    </div>
</footer>
<?php if (!empty($companyWhatsapp)): ?>
    <a class="whatsapp-float" href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp GeekHost">
        <i class="bi bi-whatsapp" aria-hidden="true"></i>
    </a>
<?php endif; ?>
<button class="scroll-top" type="button" data-scroll-top aria-label="Voltar ao topo" hidden><span aria-hidden="true">↑</span></button>
<script src="<?= e(asset('assets/js/site.js')) ?>" defer></script>
<?php if (!empty($panelScripts)): foreach ((array) $panelScripts as $src): ?>
<script src="<?= e(asset($src)) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
