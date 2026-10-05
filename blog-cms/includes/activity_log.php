<?php
/**
 * Central audit-trail writer. Every privileged action in the admin should
 * call log_activity() right after it succeeds — see each admin/*/save.php
 * etc. for call sites. Never logs secrets (passwords, tokens, raw request
 * bodies) — only a short human-readable description.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function log_activity(?int $userId, string $action, ?string $entityType, ?int $entityId, string $description): void
{
    $stmt = blog_db()->prepare(
        'INSERT INTO blog_activity_logs (user_id, action, entity_type, entity_id, description, ip_address, user_agent)
         VALUES (:user_id, :action, :entity_type, :entity_id, :description, :ip, :ua)'
    );
    $stmt->execute([
        ':user_id' => $userId,
        ':action' => $action,
        ':entity_type' => $entityType,
        ':entity_id' => $entityId,
        ':description' => mb_substr($description, 0, 500),
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':ua' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
    ]);
}

/**
 * @return array{rows: array, total: int}
 */
function activity_log_list(array $filters, int $page, int $perPage): array
{
    $pdo = blog_db();
    $where = [];
    $params = [];

    if (!empty($filters['user_id'])) {
        $where[] = 'l.user_id = :user_id';
        $params[':user_id'] = (int) $filters['user_id'];
    }
    if (!empty($filters['action'])) {
        $where[] = 'l.action = :action';
        $params[':action'] = $filters['action'];
    }
    if (!empty($filters['entity_type'])) {
        $where[] = 'l.entity_type = :entity_type';
        $params[':entity_type'] = $filters['entity_type'];
    }
    if (!empty($filters['date_from'])) {
        $where[] = 'l.created_at >= :date_from';
        $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
    }
    if (!empty($filters['date_to'])) {
        $where[] = 'l.created_at <= :date_to';
        $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM blog_activity_logs l $whereSql");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $perPage = max(1, min(100, $perPage));
    $offset = max(0, ($page - 1) * $perPage);

    $stmt = $pdo->prepare(
        "SELECT l.*, u.name AS user_name
           FROM blog_activity_logs l
           LEFT JOIN blog_users u ON u.id = l.user_id
           $whereSql
          ORDER BY l.created_at DESC
          LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);

    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}
