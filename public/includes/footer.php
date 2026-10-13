</main>
<footer class="site-footer">
    <div class="footer-inner">
        <a class="brand brand-footer" href="index.php" aria-label="GeekHost, página inicial">
            <img class="footer-emblem" src="assets/images/emblema.png" alt="" width="48" height="48">
            <span class="brand-name">geekhost<span class="brand-dot">.</span></span>
        </a>
        <p>Seu projeto merece um bom começo.</p>
        <nav class="footer-links" aria-label="Links do rodapé">
            <a href="planos.php">Planos</a>
            <a href="contato.php">Contato</a>
            <a href="gerador.php">Gerador de WhatsApp</a>
        </nav>
        <small>&copy; <?= date('Y') ?> GeekHost. Consulte as condições comerciais antes de contratar.</small>
    </div>
</footer>
<button class="scroll-top" type="button" data-scroll-top aria-label="Voltar ao topo" title="Voltar ao topo" hidden>
    <span aria-hidden="true">↑</span>
</button>
<?php if (!empty($loadQrCode)): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<?php endif; ?>
<script src="assets/js/site.js" defer></script>
</body>
</html>