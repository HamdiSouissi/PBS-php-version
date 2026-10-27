<?php

declare(strict_types=1);
/**
 * @var \App\Core\View\ViewRenderer $viewRenderer
 * @var \Modules\Authentication\Models\User $user
 */
?>
<?= $viewRenderer->pageHeader('Mon profil', [['label' => 'Paramètres']]) ?>

<div class="page-body">
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header fw-semibold">Mes informations</div>
            <div class="card-body">
                <form method="post" action="/profile" class="row g-2" data-guard>
                    <?= csrf_field() ?>
                    <div class="col-6"><label class="form-label">Prénom</label>
                        <input class="form-control" name="first_name" value="<?= e($user->firstName) ?>" required></div>
                    <div class="col-6"><label class="form-label">Nom</label>
                        <input class="form-control" name="last_name" value="<?= e($user->lastName) ?>" required></div>
                    <div class="col-12"><label class="form-label">E-mail</label>
                        <input class="form-control" value="<?= e($user->email) ?>" disabled></div>
                    <div class="col-12"><button class="btn btn-primary btn-sm"><i data-lucide="save"></i>Enregistrer</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header fw-semibold">Sécurité du compte</div>
            <div class="card-body">
                <form method="post" action="/profile/password" class="row g-2" data-guard>
                    <?= csrf_field() ?>
                    <div class="col-12"><label class="form-label">Mot de passe actuel</label>
                        <input type="password" name="current_password" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="password" class="form-control" minlength="12" required></div>
                    <div class="col-12"><label class="form-label">Confirmation</label>
                        <input type="password" name="password_confirmation" class="form-control" minlength="12" required></div>
                    <div class="col-12"><button class="btn btn-warning btn-sm"><i data-lucide="key-round"></i>Changer le mot de passe</button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header fw-semibold">Double authentification (TOTP)</div>
            <div class="card-body">
                <?php if ($user->totpEnrolled): ?>
                    <span class="badge text-bg-success mb-2">TOTP activé</span>
                    <p class="small text-muted mb-0">La double authentification est active sur ce compte.</p>
                <?php else: ?>
                    <p class="small text-muted">Générez un secret, saisissez-le manuellement dans votre application
                        d'authentification (aucune transmission à un tiers), puis confirmez avec un code.</p>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="totpBegin">
                        <i data-lucide="shield-lock"></i>Activer TOTP
                    </button>
                    <div id="totpPanel" class="mt-3 d-none">
                        <label class="form-label small" for="totpSecret">Secret (saisie manuelle)</label>
                        <input class="form-control form-control-sm mb-2" id="totpSecret" readonly>
                        <form id="totpForm" class="d-flex gap-2">
                            <input class="form-control form-control-sm" name="code" placeholder="Code à 6 chiffres" maxlength="6" required>
                            <button class="btn btn-success btn-sm">Confirmer</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</div>
<script>
document.getElementById('totpBegin')?.addEventListener('click', async () => {
    const res = await fetch('/profile/totp/begin', {method: 'POST', headers: {'X-CSRF-TOKEN': '<?= e(csrf_token()) ?>'}});
    const data = await res.json();
    document.getElementById('totpSecret').value = data.secret;
    document.getElementById('totpPanel').classList.remove('d-none');
});
document.getElementById('totpForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    const res = await fetch('/profile/totp/confirm', {method: 'POST', body: fd});
    const data = await res.json();
    if (data.success) {
        window.PBSApp?.toast('Double authentification activée', 'success');
        location.reload();
    } else {
        window.PBSApp?.toast(data.message || 'Code invalide', 'danger');
    }
});
</script>
