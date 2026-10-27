<?php

declare(strict_types=1);
/** @var \Modules\Authentication\Models\User $user */
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Mes informations</div>
            <div class="card-body">
                <form method="post" action="/profile" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-6"><label class="form-label">Prénom</label>
                        <input class="form-control" name="first_name" value="<?= e($user->firstName) ?>" required></div>
                    <div class="col-6"><label class="form-label">Nom</label>
                        <input class="form-control" name="last_name" value="<?= e($user->lastName) ?>" required></div>
                    <div class="col-12"><label class="form-label">E-mail</label>
                        <input class="form-control" value="<?= e($user->email) ?>" disabled></div>
                    <div class="col-12"><button class="btn btn-primary">Enregistrer</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Sécurité du compte</div>
            <div class="card-body">
                <form method="post" action="/profile/password" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-12"><label class="form-label">Mot de passe actuel</label>
                        <input type="password" name="current_password" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="password" class="form-control" minlength="12" required></div>
                    <div class="col-12"><label class="form-label">Confirmation</label>
                        <input type="password" name="password_confirmation" class="form-control" minlength="12" required></div>
                    <div class="col-12"><button class="btn btn-warning"><i class="bi bi-key me-1"></i>Changer le mot de passe</button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Double authentification (TOTP)</div>
            <div class="card-body">
                <?php if ($user->totpEnrolled): ?>
                    <span class="badge text-bg-success mb-2">TOTP activé</span>
                    <p class="small text-muted mb-2">La double authentification est active sur ce compte.</p>
                <?php else: ?>
                    <p class="small text-muted">Générez un secret, ajoutez-le dans votre application TOTP, puis confirmez avec un code.</p>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="totpBegin">
                        <i class="bi bi-shield-lock me-1"></i>Activer TOTP
                    </button>
                    <div id="totpPanel" class="mt-3 d-none">
                        <label class="form-label small">Secret (saisie manuelle)</label>
                        <input class="form-control form-control-sm mb-2" id="totpSecret" readonly>
                        <img id="totpQr" alt="QR code TOTP" class="mb-2" style="max-width:180px">
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
<script>
document.getElementById('totpBegin')?.addEventListener('click', async () => {
    const res = await fetch('/profile/totp/begin', {method: 'POST', headers: {'X-CSRF-TOKEN': '<?= e(csrf_token()) ?>'}});
    const data = await res.json();
    document.getElementById('totpSecret').value = data.secret;
    document.getElementById('totpQr').src = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(data.uri);
    document.getElementById('totpPanel').classList.remove('d-none');
});
document.getElementById('totpForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append('_csrf', '<?= e(csrf_token()) ?>');
    const res = await fetch('/profile/totp/confirm', {method: 'POST', body: fd});
    const data = await res.json();
    if (data.success) { location.reload(); } else { alert(data.message || 'Code invalide'); }
});
</script>
