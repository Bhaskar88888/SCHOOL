<?php
/**
 * includes/widgets/inbox_widget.php
 * Compact in-panel inbox widget — shows last 5 message threads.
 * Usage: include __DIR__ . '/../includes/widgets/inbox_widget.php';
 */
$_widgetUserId = get_current_user_id();
$_widgetThreads = [];

if (db_table_exists('message_threads') && db_table_exists('thread_participants') && db_table_exists('messages')) {
    $_widgetThreads = db_fetchAll(
        "SELECT mt.id, mt.subject,
                (SELECT m2.body FROM messages m2 WHERE m2.thread_id=mt.id AND m2.is_deleted=0 ORDER BY m2.created_at DESC LIMIT 1) AS last_body,
                (SELECT m2.created_at FROM messages m2 WHERE m2.thread_id=mt.id AND m2.is_deleted=0 ORDER BY m2.created_at DESC LIMIT 1) AS last_at,
                (SELECT u2.name FROM messages m2 JOIN users u2 ON m2.sender_id=u2.id WHERE m2.thread_id=mt.id AND m2.is_deleted=0 ORDER BY m2.created_at DESC LIMIT 1) AS last_sender,
                (SELECT COUNT(*) FROM messages m3 WHERE m3.thread_id=mt.id AND m3.sender_id!=? AND m3.is_deleted=0 AND (tp.last_read_at IS NULL OR m3.created_at > tp.last_read_at)) AS unread
         FROM message_threads mt
         JOIN thread_participants tp ON tp.thread_id=mt.id AND tp.user_id=?
         ORDER BY COALESCE(last_at, mt.created_at) DESC
         LIMIT 5",
        [$_widgetUserId, $_widgetUserId]
    );
}
?>
<div style="background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:18px 20px;margin-top:22px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted)">✉ Messages</div>
        <a href="<?= BASE_URL ?>/messages.php" style="font-size:12px;color:var(--accent);font-weight:600;text-decoration:none">View all →</a>
    </div>

    <?php if (empty($_widgetThreads)): ?>
        <div style="color:var(--text-muted);font-size:13px;text-align:center;padding:16px 0">
            No messages yet.<br>
            <a href="<?= BASE_URL ?>/messages.php" style="color:var(--accent);font-weight:600">Compose one →</a>
        </div>
    <?php else: ?>
        <?php foreach ($_widgetThreads as $_wt):
            $_unread = (int)($_wt['unread'] ?? 0);
            $_snippet = htmlspecialchars(mb_substr($_wt['last_body'] ?? '', 0, 60)) . (mb_strlen($_wt['last_body'] ?? '') > 60 ? '…' : '');
        ?>
        <a href="<?= BASE_URL ?>/messages.php?thread=<?= (int)$_wt['id'] ?>"
           style="display:flex;gap:10px;align-items:flex-start;padding:9px 0;border-bottom:1px solid var(--border);text-decoration:none;color:inherit;<?= $_unread > 0 ? 'font-weight:700;' : '' ?>">
            <div style="width:36px;height:36px;border-radius:50%;background:rgba(99,102,241,.15);color:var(--accent);display:grid;place-items:center;font-weight:800;font-size:14px;flex-shrink:0">
                <?= strtoupper(substr($_wt['last_sender'] ?? '?', 0, 1)) ?>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                    <?= htmlspecialchars($_wt['subject'] ?? 'No Subject') ?>
                    <?php if ($_unread > 0): ?>
                        <span style="background:#6366f1;color:#fff;border-radius:999px;padding:1px 6px;font-size:10px;margin-left:5px"><?= $_unread ?></span>
                    <?php endif; ?>
                </div>
                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= $_snippet ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="margin-top:12px">
        <a href="<?= BASE_URL ?>/messages.php"
           style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:999px;font-size:12px;font-weight:600;background:rgba(99,102,241,.1);color:var(--accent);text-decoration:none;transition:background .15s"
           onmouseover="this.style.background='var(--accent)';this.style.color='#fff'"
           onmouseout="this.style.background='rgba(99,102,241,.1)';this.style.color='var(--accent)'">
            + Compose New Message
        </a>
    </div>
</div>
