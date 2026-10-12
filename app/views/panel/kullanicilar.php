<?php /** @var array $users @var string $q @var string $role @var array $me @var ?array $resetLink @var int $total */ ?>
<?php if ($resetLink): ?>
<div class="card">
  <div class="card__body">
    <b><?= e($resetLink[0]) ?></b> için şifre sıfırlama bağlantısı (2 gün geçerli, tek kullanımlık). Kullanıcıya kendin ilet:
    <div class="copy"><input value="<?= e($resetLink[1]) ?>" readonly aria-label="Sıfırlama bağlantısı" data-copy><button class="btn btn--ghost btn--sm js-only" type="button" data-copy-btn><?= icon('copy', 'icon icon--sm') ?>Kopyala</button></div>
  </div>
</div>
<?php endif ?>

<form class="ptools" method="get" action="<?= e(url('panel/kullanicilar')) ?>" data-autosubmit>
  <label class="fsearch"><?= icon('search', 'icon icon--sm') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Ad ya da e-posta…" aria-label="Kullanıcı ara"></label>
  <span class="input"><select name="rol" aria-label="Rol"><option value="">Tüm roller</option>
    <?php foreach (Auth::ROLES as $k => $l): ?><option value="<?= $k ?>"<?= $role === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?>
  </select></span>
  <button class="btn btn--ghost btn--sm no-js-only" type="submit">Süz</button>
  <span class="hint"><?= count_fmt($total) ?> kullanıcı</span>
</form>

<form method="post" action="<?= e(url('panel/kullanicilar', ['q' => $q ?: null, 'rol' => $role ?: null])) ?>">
  <?= csrf_field() ?>
  <button type="submit" disabled hidden aria-hidden="true" tabindex="-1"></button>
  <div class="dtable-wrap">
    <table class="dtable">
      <thead><tr><th>Kullanıcı</th><th>Rol</th><th class="num">Öneri</th><th>Son giriş</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): $self = (int) $u['id'] === (int) $me['id']; ?>
        <tr<?= (int) $u['blocked'] ? ' style="opacity:.55"' : '' ?>>
          <td><span class="cell-item"><span class="avatar"><?= e(initial($u['username'])) ?></span><span><b><?= e($u['username']) ?></b><small><?= $self ? 'sen' : e($u['email'] ?? '—') ?><?= (int) $u['blocked'] ? ' · engelli' : '' ?></small></span></span></td>
          <td>
            <span style="display:flex;gap:6px;align-items:center">
              <span class="input input--sm"><select name="rol[<?= (int) $u['id'] ?>]" aria-label="Rol"<?= $self ? ' disabled' : '' ?> data-role-select>
                <?php foreach (Auth::ROLES as $k => $l): ?><option value="<?= $k ?>"<?= $u['role'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?>
              </select></span>
              <?php if (!$self): ?><button class="btn btn--ghost btn--sm" type="submit" name="rol_kaydet" value="<?= (int) $u['id'] ?>" data-role-save>Kaydet</button><?php endif ?>
            </span>
          </td>
          <td class="num"><?= (int) $u['n'] ?: '—' ?><?php if ((int) $u['r'] > 0): ?> <span class="trust">%<?= (int) round($u['d'] / $u['r'] * 100) ?> onay</span><?php endif ?></td>
          <td class="muted"><?= $u['last_login'] ? e(ago((int) $u['last_login'])) : '—' ?></td>
          <td class="act"><?php if (!$self): ?>
            <button class="icon-btn icon-btn--sm" type="submit" name="sifirla" value="<?= (int) $u['id'] ?>" aria-label="Şifre sıfırlama bağlantısı" title="Şifre sıfırlama bağlantısı oluştur"><?= icon('lock') ?></button>
            <button class="icon-btn icon-btn--sm btn--danger" type="submit" name="engelle" value="<?= (int) $u['id'] ?>" aria-label="<?= (int) $u['blocked'] ? 'Engeli kaldır' : 'Engelle' ?>" title="<?= (int) $u['blocked'] ? 'Engeli kaldır' : 'Engelle' ?>"<?= (int) $u['blocked'] ? '' : ' data-confirm="' . e($u['username']) . ' engellensin mi?"' ?>><?= icon((int) $u['blocked'] ? 'refresh' : 'no') ?></button>
          <?php endif ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>
</form>

<details class="card addgroup" style="margin-top:20px"<?= isset($_GET['ekle']) ? ' open' : '' ?>>
  <summary class="card__head"><span class="group__icon"><?= icon('plus') ?></span><h3>Kullanıcı ekle</h3><?= icon('chevron', 'icon icon--sm chev') ?></summary>
  <form method="post" action="<?= e(url('panel/kullanicilar')) ?>" class="card__body fields">
    <?= csrf_field() ?>
    <label class="field"><span class="lbl">Kullanıcı adı</span><span class="input"><input name="kullanici" required minlength="3" maxlength="32" autocomplete="off"></span></label>
    <label class="field"><span class="lbl">E-posta (isteğe bağlı)</span><span class="input"><input type="email" name="eposta" autocomplete="off"></span></label>
    <label class="field"><span class="lbl">Rol</span><span class="input"><select name="yeni_rol"><?php foreach (Auth::ROLES as $k => $l): ?><option value="<?= $k ?>"<?= $k === 'editor' ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach ?></select></span></label>
    <label class="field"><span class="lbl">Geçici şifre</span><span class="input"><input type="text" name="sifre" required minlength="8" autocomplete="new-password"></span><span class="hint">En az 8 karakter; kullanıcıya ilet</span></label>
    <div class="field field--full" style="align-items:flex-end"><button class="btn btn--sm" type="submit" name="ekle" value="1">Ekle</button></div>
  </form>
</details>
