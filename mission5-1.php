<?php

declare(strict_types=1);

session_start();

const TABLE_NAME = 'tbtest';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function postString(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function redirectTo(string $path = './mission5-1.php'): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function verifyStoredPassword(string $plainPassword, string $storedPassword): bool
{
    $passwordInfo = password_get_info($storedPassword);

    if (($passwordInfo['algo'] ?? null) !== null) {
        return password_verify($plainPassword, $storedPassword);
    }

    // 旧版で平文保存されていたデータとの互換用。認証後の更新時にハッシュ化されます。
    return hash_equals($storedPassword, $plainPassword);
}

function findPost(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT id, name, comment, password, date FROM ' . TABLE_NAME . ' WHERE id = :id');
    $statement->execute(['id' => $id]);
    $post = $statement->fetch();

    return is_array($post) ? $post : null;
}

$dsn = getenv('DB_DSN') ?: '';
$dbUser = getenv('DB_USER') ?: '';
$dbPassword = getenv('DB_PASSWORD') ?: '';

if ($dsn === '' || $dbUser === '') {
    http_response_code(500);
    echo 'Database configuration is missing. Set DB_DSN, DB_USER, and DB_PASSWORD.';
    exit;
}

try {
    $pdo = new PDO(
        $dsn,
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . TABLE_NAME . ' ('
        . 'id INT AUTO_INCREMENT PRIMARY KEY,'
        . 'name VARCHAR(32) NOT NULL,'
        . 'comment TEXT NOT NULL,'
        . 'password VARCHAR(255) NOT NULL,'
        . 'date DATETIME NOT NULL'
        . ') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) $_SESSION['csrf_token'], $csrfToken)) {
        http_response_code(403);
        echo 'Invalid CSRF token.';
        exit;
    }

    $action = postString('action');

    try {
        if ($action === 'create') {
            $name = postString('name');
            $comment = postString('comment');
            $password = postString('password');

            if ($name === '' || $comment === '' || $password === '') {
                flash('名前・コメント・パスワードをすべて入力してください。', 'error');
                redirectTo();
            }

            if (mb_strlen($name) > 32) {
                flash('名前は32文字以内で入力してください。', 'error');
                redirectTo();
            }

            $statement = $pdo->prepare(
                'INSERT INTO ' . TABLE_NAME . ' (name, comment, password, date) '
                . 'VALUES (:name, :comment, :password, :date)'
            );
            $statement->execute([
                'name' => $name,
                'comment' => $comment,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'date' => date('Y-m-d H:i:s'),
            ]);

            flash('投稿を受け付けました。', 'success');
            redirectTo();
        }

        if ($action === 'delete') {
            $id = filter_input(INPUT_POST, 'delete_number', FILTER_VALIDATE_INT);
            $password = postString('delete_password');

            if (!is_int($id) || $id < 1 || $password === '') {
                flash('削除対象の番号とパスワードを入力してください。', 'error');
                redirectTo();
            }

            $post = findPost($pdo, $id);
            if ($post === null) {
                flash('指定された投稿は存在しません。', 'error');
                redirectTo();
            }

            if (!verifyStoredPassword($password, (string) $post['password'])) {
                flash('パスワードが一致しません。', 'error');
                redirectTo();
            }

            $statement = $pdo->prepare('DELETE FROM ' . TABLE_NAME . ' WHERE id = :id');
            $statement->execute(['id' => $id]);

            flash('指定された投稿を削除しました。', 'success');
            redirectTo();
        }

        if ($action === 'prepare_edit') {
            $id = filter_input(INPUT_POST, 'edit_number', FILTER_VALIDATE_INT);
            $password = postString('edit_password');

            if (!is_int($id) || $id < 1 || $password === '') {
                flash('編集対象の番号とパスワードを入力してください。', 'error');
                redirectTo();
            }

            $post = findPost($pdo, $id);
            if ($post === null || !verifyStoredPassword($password, (string) $post['password'])) {
                flash('投稿番号またはパスワードが正しくありません。', 'error');
                redirectTo();
            }

            $_SESSION['edit_authorized_id'] = $id;
            redirectTo('./mission5-1.php?edit=1');
        }

        if ($action === 'update') {
            $authorizedId = $_SESSION['edit_authorized_id'] ?? null;
            if (!is_int($authorizedId)) {
                flash('編集認証の有効期限が切れています。', 'error');
                redirectTo();
            }

            $name = postString('name');
            $comment = postString('comment');
            $newPassword = postString('new_password');

            if ($name === '' || $comment === '') {
                flash('名前とコメントを入力してください。', 'error');
                redirectTo('./mission5-1.php?edit=1');
            }

            if (mb_strlen($name) > 32) {
                flash('名前は32文字以内で入力してください。', 'error');
                redirectTo('./mission5-1.php?edit=1');
            }

            $currentPost = findPost($pdo, $authorizedId);
            if ($currentPost === null) {
                unset($_SESSION['edit_authorized_id']);
                flash('編集対象の投稿が見つかりません。', 'error');
                redirectTo();
            }

            $passwordHash = $newPassword !== ''
                ? password_hash($newPassword, PASSWORD_DEFAULT)
                : (string) $currentPost['password'];

            $statement = $pdo->prepare(
                'UPDATE ' . TABLE_NAME . ' '
                . 'SET name = :name, comment = :comment, password = :password, date = :date '
                . 'WHERE id = :id'
            );
            $statement->execute([
                'name' => $name,
                'comment' => $comment,
                'password' => $passwordHash,
                'date' => date('Y-m-d H:i:s'),
                'id' => $authorizedId,
            ]);

            unset($_SESSION['edit_authorized_id']);
            flash('指定された投稿を編集しました。', 'success');
            redirectTo();
        }

        flash('不明な操作です。', 'error');
        redirectTo();
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        flash('データベース処理に失敗しました。', 'error');
        redirectTo();
    }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$editingPost = null;
if (isset($_GET['edit']) && is_int($_SESSION['edit_authorized_id'] ?? null)) {
    $editingPost = findPost($pdo, (int) $_SESSION['edit_authorized_id']);
    if ($editingPost === null) {
        unset($_SESSION['edit_authorized_id']);
    }
}

$posts = $pdo->query(
    'SELECT id, name, comment, date FROM ' . TABLE_NAME . ' ORDER BY id DESC'
)->fetchAll();
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PHP Bulletin Board</title>
    <style>
        body { max-width: 760px; margin: 40px auto; padding: 0 16px; font-family: sans-serif; line-height: 1.6; }
        form, article { border: 1px solid #ccc; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        label { display: block; margin-top: 10px; font-weight: 700; }
        input, textarea, button { box-sizing: border-box; width: 100%; padding: 8px; }
        textarea { min-height: 110px; resize: vertical; }
        button { margin-top: 14px; cursor: pointer; }
        .flash { padding: 12px; border: 1px solid #999; border-radius: 8px; }
        .flash.success { border-color: #18794e; }
        .flash.error { border-color: #b42318; }
        .meta { font-size: 0.9rem; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        @media (max-width: 640px) { .actions { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<h1>PHP Bulletin Board</h1>

<?php if (is_array($flash)): ?>
    <p class="flash <?= h((string) $flash['type']) ?>"><?= h((string) $flash['message']) ?></p>
<?php endif; ?>

<form method="post">
    <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['csrf_token']) ?>">
    <input type="hidden" name="action" value="<?= $editingPost ? 'update' : 'create' ?>">

    <h2><?= $editingPost ? '投稿を編集' : '新規投稿' ?></h2>

    <label for="name">名前</label>
    <input id="name" type="text" name="name" maxlength="32" required
           value="<?= h((string) ($editingPost['name'] ?? '')) ?>">

    <label for="comment">コメント</label>
    <textarea id="comment" name="comment" required><?= h((string) ($editingPost['comment'] ?? '')) ?></textarea>

    <?php if ($editingPost): ?>
        <label for="new_password">新しいパスワード（変更しない場合は空欄）</label>
        <input id="new_password" type="password" name="new_password" autocomplete="new-password">
    <?php else: ?>
        <label for="password">編集・削除用パスワード</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">
    <?php endif; ?>

    <button type="submit"><?= $editingPost ? '更新する' : '投稿する' ?></button>
</form>

<div class="actions">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="delete">
        <h2>投稿を削除</h2>
        <label for="delete_number">投稿番号</label>
        <input id="delete_number" type="number" name="delete_number" min="1" required>
        <label for="delete_password">パスワード</label>
        <input id="delete_password" type="password" name="delete_password" required autocomplete="current-password">
        <button type="submit">削除する</button>
    </form>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h((string) $_SESSION['csrf_token']) ?>">
        <input type="hidden" name="action" value="prepare_edit">
        <h2>投稿を編集</h2>
        <label for="edit_number">投稿番号</label>
        <input id="edit_number" type="number" name="edit_number" min="1" required>
        <label for="edit_password">パスワード</label>
        <input id="edit_password" type="password" name="edit_password" required autocomplete="current-password">
        <button type="submit">編集画面を開く</button>
    </form>
</div>

<section>
    <h2>投稿一覧</h2>
    <?php if ($posts === []): ?>
        <p>投稿はまだありません。</p>
    <?php endif; ?>

    <?php foreach ($posts as $post): ?>
        <article>
            <p class="meta">#<?= (int) $post['id'] ?> / <?= h((string) $post['name']) ?> / <?= h((string) $post['date']) ?></p>
            <p><?= nl2br(h((string) $post['comment'])) ?></p>
        </article>
    <?php endforeach; ?>
</section>
</body>
</html>
