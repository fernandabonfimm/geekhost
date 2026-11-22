<?php if (!empty($requireAdminAuth)): ?>
    </main>
</div>
<?php else: ?>
</main>
<?php endif; ?>
<script>
(() => {
  const body = document.body;
  const theme = localStorage.getItem('gh-admin-theme') || 'light';
  document.documentElement.setAttribute('data-theme', theme);
  const lucideBoot = () => { if (window.lucide) window.lucide.createIcons(); };
  document.addEventListener('DOMContentLoaded', lucideBoot);
  setTimeout(lucideBoot, 300);

  body.querySelector('[data-admin-theme]')?.addEventListener('click', () => {
    const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('gh-admin-theme', next);
  });
  body.querySelector('[data-admin-collapse]')?.addEventListener('click', () => {
    body.classList.toggle('is-collapsed');
  });
  body.querySelector('[data-admin-nav-open]')?.addEventListener('click', () => {
    body.classList.add('is-nav-open');
  });
  body.querySelector('[data-admin-nav-close]')?.addEventListener('click', () => {
    body.classList.remove('is-nav-open');
  });
  body.querySelector('[data-admin-period]')?.addEventListener('change', (e) => {
    const url = new URL(window.location.href);
    url.searchParams.set('period', e.target.value);
    window.location.href = url.toString();
  });
})();
</script>
<?php geekhost_client_config(!empty($adminUser) ? $adminGate->auth()->currentSessionToken() : null); ?>
<script src="<?= e(asset('assets/js/api.js')) ?>" defer></script>
<script src="<?= e(asset('assets/js/password-toggle.js')) ?>" defer></script>
<?php if (!empty($adminScripts)): foreach ((array) $adminScripts as $src): ?>
<script src="<?= e(asset($src)) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
