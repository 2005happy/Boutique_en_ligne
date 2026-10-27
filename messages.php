<?php
// messages.php
require_once 'config/init.php';
require_once 'config/messagerie.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=messages.php");
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Envoyer un message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer'])) {
    $destinataire_id = (int)($_POST['destinataire_id'] ?? 0);
    $contenu = trim($_POST['contenu'] ?? '');

    if ($destinataire_id > 0 && $destinataire_id !== $uid && $contenu !== '') {
        $conv_id = get_ou_creer_conversation($pdo, $uid, $destinataire_id);
        envoyer_message($pdo, $conv_id, $uid, $contenu);
        notifier($pdo, $destinataire_id, 'message', "Nouveau message de " . $_SESSION['nom'], "messages.php?conv=$conv_id");
    }
    header("Location: messages.php?conv=$conv_id");
    exit;
}

// Marquer les messages comme lus
if (isset($_GET['conv'])) {
    $conv_id = (int)$_GET['conv'];
    $pdo->prepare("UPDATE messages SET lu = 1 WHERE conversation_id = ? AND expediteur_id != ?")
        ->execute([$conv_id, $uid]);
}

// Liste des conversations
$stmt = $pdo->prepare("
    SELECT c.*,
        CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END AS autre_id,
        (SELECT contenu FROM messages WHERE conversation_id = c.id ORDER BY date_envoi DESC LIMIT 1) AS dernier_message,
        (SELECT date_envoi FROM messages WHERE conversation_id = c.id ORDER BY date_envoi DESC LIMIT 1) AS derniere_date,
        (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND expediteur_id != ? AND lu = 0) AS non_lus
    FROM conversations c
    WHERE c.user1_id = ? OR c.user2_id = ?
    ORDER BY c.date_maj DESC
");
$stmt->execute([$uid, $uid, $uid, $uid]);
$conversations = $stmt->fetchAll();

// Charger la conversation active
$conv_active = null;
$messages = [];
$autre_user = null;
if (isset($_GET['conv'])) {
    $conv_id = (int)$_GET['conv'];
    $stmt = $pdo->prepare("SELECT * FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
    $stmt->execute([$conv_id, $uid, $uid]);
    $conv_active = $stmt->fetch();

    if ($conv_active) {
        $autre_id = ($conv_active['user1_id'] == $uid) ? $conv_active['user2_id'] : $conv_active['user1_id'];
        $stmt = $pdo->prepare("SELECT id, nom, role FROM utilisateurs WHERE id = ?");
        $stmt->execute([$autre_id]);
        $autre_user = $stmt->fetch();

        $stmt = $pdo->prepare("
            SELECT m.*, u.nom AS expediteur_nom
            FROM messages m
            LEFT JOIN utilisateurs u ON m.expediteur_id = u.id
            WHERE m.conversation_id = ?
            ORDER BY m.date_envoi ASC
        ");
        $stmt->execute([$conv_id]);
        $messages = $stmt->fetchAll();
    }
}

// Liste des utilisateurs à qui écrire (tous sauf soi)
$stmt = $pdo->prepare("SELECT id, nom, role FROM utilisateurs WHERE id != ? AND statut = 'actif' ORDER BY nom");
$stmt->execute([$uid]);
$tous_users = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="messages-layout">
    <!-- Liste des conversations -->
    <aside class="conversations-sidebar">
        <div class="conv-header">
            <h2>💬 Messages</h2>
            <button onclick="ouvrirNouveauMessage()" class="btn-icon" title="Nouveau message">➕</button>
        </div>

        <?php if (empty($conversations)): ?>
            <p class="empty" style="padding:20px;font-size:0.85rem;text-align:center;">
                Aucune conversation.<br>
                Cliquez sur ➕ pour démarrer.
            </p>
        <?php else: ?>
            <ul class="conv-list">
                <?php foreach ($conversations as $c):
                    $stmt = $pdo->prepare("SELECT nom FROM utilisateurs WHERE id = ?");
                    $stmt->execute([$c['autre_id']]);
                    $nom_autre = $stmt->fetchColumn() ?: 'Utilisateur';
                ?>
                    <li class="conv-item <?= (isset($_GET['conv']) && (int)$_GET['conv'] === (int)$c['id']) ? 'active' : '' ?>">
                        <a href="?conv=<?= $c['id'] ?>">
                            <span class="conv-avatar"><?= strtoupper(substr($nom_autre, 0, 1)) ?></span>
                            <div class="conv-info">
                                <strong><?= htmlspecialchars($nom_autre) ?></strong>
                                <small><?= htmlspecialchars(mb_substr($c['dernier_message'] ?? 'Aucun message', 0, 30)) ?></small>
                            </div>
                            <?php if ($c['non_lus'] > 0): ?>
                                <span class="conv-badge"><?= $c['non_lus'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </aside>

    <!-- Conversation active -->
    <main class="chat-panel">
        <?php if ($conv_active && $autre_user): ?>
            <div class="chat-header">
                <span class="conv-avatar"><?= strtoupper(substr($autre_user['nom'], 0, 1)) ?></span>
                <div>
                    <strong><?= htmlspecialchars($autre_user['nom']) ?></strong>
                    <small class="text-muted"><?= ucfirst($autre_user['role']) ?></small>
                </div>
            </div>

            <div class="chat-messages" id="chat-messages">
                <?php foreach ($messages as $m):
                    $est_moi = ((int)$m['expediteur_id'] === $uid);
                ?>
                    <div class="chat-message <?= $est_moi ? 'me' : 'other' ?>">
                        <div class="chat-bubble">
                            <?= nl2br(htmlspecialchars($m['contenu'])) ?>
                            <div class="chat-time"><?= date('H:i', strtotime($m['date_envoi'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($messages)): ?>
                    <p class="empty" style="text-align:center;padding:40px;">
                        Aucun message. Commencez la conversation !
                    </p>
                <?php endif; ?>
            </div>

            <form method="POST" action="messages.php?conv=<?= $conv_active['id'] ?>" class="chat-form">
                <input type="hidden" name="destinataire_id" value="<?= $autre_user['id'] ?>">
                <input type="text" name="contenu" placeholder="Écrivez votre message..." required autofocus>
                <button type="submit" name="envoyer" class="btn-submit">📤 Envoyer</button>
            </form>
        <?php else: ?>
            <div class="chat-empty">
                <p style="font-size:3rem;">💬</p>
                <h3>Sélectionnez une conversation</h3>
                <p class="text-muted">ou cliquez sur ➕ pour en démarrer une.</p>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Modale nouveau message -->
<div id="modalNouveau" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <h2>Nouveau message</h2>
        <form method="POST" action="messages.php" id="formNouveau">
            <div class="form-group">
                <label for="destinataire_id">Destinataire :</label>
                <select name="destinataire_id" id="destinataire_id" required>
                    <option value="">— Choisir —</option>
                    <?php foreach ($tous_users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>">
                            <?= htmlspecialchars($u['nom']) ?> (<?= $u['role'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="contenu">Message :</label>
                <textarea name="contenu" id="contenu" rows="4" required></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="fermerModal()" class="btn-secondary">Annuler</button>
                <button type="submit" name="envoyer" value="1" class="btn-submit">Envoyer</button>
            </div>
        </form>
    </div>
</div>

<script>
// Ouvrir automatiquement la première conversation non lue
<?php if (!isset($_GET['conv']) && !empty($conversations)): ?>
    // Optionnel : rediriger vers la première conversation
<?php endif; ?>

// Scroll bas
const chatMsgs = document.getElementById('chat-messages');
if (chatMsgs) chatMsgs.scrollTop = chatMsgs.scrollHeight;

// Modale
function ouvrirNouveauMessage() {
    document.getElementById('modalNouveau').style.display = 'flex';
}
function fermerModal() {
    document.getElementById('modalNouveau').style.display = 'none';
}

<?php if ($conv_active): ?>
// Rafraîchissement automatique toutes les 3 secondes
setInterval(() => {
    fetch('ajax_messages.php?conv=<?= $conv_active['id'] ?>')
        .then(r => r.json())
        .then(data => {
            const container = document.getElementById('chat-messages');
            if (data.length !== container.querySelectorAll('.chat-message').length) {
                container.innerHTML = data.map(m => `
                    <div class="chat-message ${m.moi ? 'me' : 'other'}">
                        <div class="chat-bubble">
                            ${m.contenu.replace(/\n/g, '<br>')}
                            <div class="chat-time">${m.heure}</div>
                        </div>
                    </div>
                `).join('');
                container.scrollTop = container.scrollHeight;
            }
        });
}, 3000);
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>