<?php
/**
 * Server-side role-based access control. Every admin script that performs a
 * privileged action calls one of these — UI buttons being hidden for a role
 * is a courtesy, never the actual control (per project requirement: "Never
 * rely only on hiding UI buttons").
 *
 * Roles (least to most privileged): author < editor < super_admin.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

const ROLE_AUTHOR = 'author';
const ROLE_EDITOR = 'editor';
const ROLE_SUPER_ADMIN = 'super_admin';

/** @return array{super_admin: bool, editor: bool, author: bool} what this role can act as, inclusive */
function perm_role_rank(string $role): int
{
    switch ($role) {
        case ROLE_SUPER_ADMIN:
            return 3;
        case ROLE_EDITOR:
            return 2;
        case ROLE_AUTHOR:
            return 1;
        default:
            return 0;
    }
}

function perm_has_role_at_least(array $user, string $minRole): bool
{
    return perm_role_rank($user['role']) >= perm_role_rank($minRole);
}

function perm_require_role(string $minRole): array
{
    $user = auth_require_login();
    if (!perm_has_role_at_least($user, $minRole)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
    return $user;
}

/** Can this user manage (edit/delete/publish) a specific post? Authors may only touch their own. */
function perm_can_manage_post(array $user, array $post): bool
{
    if (perm_has_role_at_least($user, ROLE_EDITOR)) {
        return true;
    }
    return $user['role'] === ROLE_AUTHOR && (int) $post['author_id'] === (int) $user['id'];
}

/** Can this user publish (vs. only save as draft)? Configurable per project requirement
 *  ("Publishing permission can be configurable") — currently: editor+ only. */
function perm_can_publish(array $user): bool
{
    return perm_has_role_at_least($user, ROLE_EDITOR);
}

function perm_can_manage_users(array $user): bool
{
    return $user['role'] === ROLE_SUPER_ADMIN;
}

function perm_can_manage_categories(array $user): bool
{
    return perm_has_role_at_least($user, ROLE_EDITOR);
}

function perm_can_view_activity_logs(array $user): bool
{
    return perm_has_role_at_least($user, ROLE_EDITOR);
}

function perm_can_restore_revisions(array $user, array $post): bool
{
    return perm_can_manage_post($user, $post);
}
