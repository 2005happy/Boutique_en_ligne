</main>
<footer class="main-footer">
    <div class="container">
        <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(param('nom_boutique', 'Ma Boutique')) ?> — Tous droits réservés.</p>
        <?php if (param('email_contact', '') !== '' || param('telephone', '') !== ''): ?>
            <p>
                <?php if (param('email_contact', '') !== ''): ?>
                    <a href="mailto:<?= htmlspecialchars(param('email_contact'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars(param('email_contact')) ?>
                    </a>
                <?php endif; ?>
                <?php if (param('email_contact', '') !== '' && param('telephone', '') !== ''): ?> · <?php endif; ?>
                <?php if (param('telephone', '') !== ''): ?>
                    <a href="tel:<?= htmlspecialchars(param('telephone'), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars(param('telephone')) ?>
                    </a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <!-- <p>Projet de Programmation Web — HTML / CSS / PHP / MySQL</p> -->
    </div>
</footer>
</body>
</html>
