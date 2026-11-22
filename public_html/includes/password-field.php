<?php

declare(strict_types=1);

/**
 * Password input with a show/hide control. Reuse on every password field.
 *
 * @param array{id?:string,required?:bool,minlength?:int,maxlength?:int,autocomplete?:string,inputmode?:string,placeholder?:string,value?:string} $opts
 */
function geekhost_password_input(string $name, array $opts = []): void
{
    $id = isset($opts['id']) ? (string) $opts['id'] : '';
    $autocomplete = (string) ($opts['autocomplete'] ?? 'current-password');
    $placeholder = (string) ($opts['placeholder'] ?? '');
    $value = (string) ($opts['value'] ?? '');
    ?>
    <span class="gh-pass-control">
        <input
            type="password"
            name="<?= e($name) ?>"
            <?php if ($id !== ''): ?>id="<?= e($id) ?>"<?php endif; ?>
            <?php if (!empty($opts['required'])): ?>required<?php endif; ?>
            <?php if (!empty($opts['minlength'])): ?>minlength="<?= (int) $opts['minlength'] ?>"<?php endif; ?>
            <?php if (!empty($opts['maxlength'])): ?>maxlength="<?= (int) $opts['maxlength'] ?>"<?php endif; ?>
            <?php if (!empty($opts['inputmode'])): ?>inputmode="<?= e((string) $opts['inputmode']) ?>"<?php endif; ?>
            autocomplete="<?= e($autocomplete) ?>"
            <?php if ($placeholder !== ''): ?>placeholder="<?= e($placeholder) ?>"<?php endif; ?>
            <?php if ($value !== ''): ?>value="<?= e($value) ?>"<?php endif; ?>
        >
        <button class="gh-pass-toggle" type="button" data-password-toggle aria-pressed="false" aria-label="Mostrar senha">
            <svg class="gh-pass-icon gh-pass-icon-show" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
            <svg class="gh-pass-icon gh-pass-icon-hide" viewBox="0 0 24 24" aria-hidden="true" hidden><path d="M3 3l18 18M10.6 10.6A3 3 0 0 0 12 15a3 3 0 0 0 2.4-4.4M9.9 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a18.2 18.2 0 0 1-4.1 4.9M6.1 6.1C3.8 7.8 2 12 2 12s3.5 7 10 7a10.6 10.6 0 0 0 4.2-.9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </button>
    </span>
    <?php
}
