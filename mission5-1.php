<?php
$dsn = 'mysql:dbname=*****;host=*******';
$user = '****';
$password = '*******';
$pdo = new PDO($dsn, $user, $password, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING));
$sql = "CREATE TABLE IF NOT EXISTS tbtest"
    . " ("
    . "id INT AUTO_INCREMENT PRIMARY KEY,"
    . "name CHAR(32),"
    . "comment TEXT,"
    . "password TEXT,"
    . "date TEXT"
    . ");";
$stmt = $pdo->query($sql);
$count = 0;
$name = '';
$edit_post_id = '';
$edit_name = '';
$edit_comment = '';
$edit_target = '';
$edit_mode = '';
$password = '';
$delete_password = '';
$edit_password = '';
$edit_post_id = isset($_POST["edit_post_id"]) ? htmlspecialchars($_POST["edit_post_id"]) : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars($_POST["name"]);
    $comment = htmlspecialchars($_POST["comment"]);
    $password = htmlspecialchars($_POST["password"]);
    $delete_password = htmlspecialchars($_POST["delete_password"]);
    $edit_password = htmlspecialchars($_POST["edit_password"]);
    $delete = htmlspecialchars($_POST["delete_number"]);
    $edit_number = htmlspecialchars($_POST["edit_number"]);
    $edit_post_id = isset($_POST["edit_post_id"]) ? htmlspecialchars($_POST["edit_post_id"]) : '';
    $edit_target = isset($_POST["edit_target"]) ? htmlspecialchars($_POST["edit_target"]) : '';
    $edit_mode = htmlspecialchars($_POST["edit_mode"]);

    // 新規投稿または編集
    if (!empty($comment) && !empty($name) && !empty($password)) {
        $date = date("Y/m/d H:i:s");

        // 編集モードの場合
        if ($edit_mode == '1') {
            // ファイルに書き込み
            $id = $edit_target;
            $sql = 'UPDATE tbtest SET name=:name,comment=:comment,password=:password,date=:date WHERE id=:id';
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
            $stmt->bindParam(':password', $password, PDO::PARAM_STR);
            $stmt->bindParam(':id', $edit_target, PDO::PARAM_INT);
            $stmt->bindParam(':date', $date, PDO::PARAM_STR);
            $stmt->execute();
            echo "指定された投稿を編集しました。";

            // 編集モードを解除する
            $edit_post_id = '';
            $edit_name = '';
            $edit_comment = '';
        } else {
            // 新規投稿の場合
            $date = date("Y/m/d H:i:s");
            $sql = "INSERT INTO tbtest (name, comment, password, date) VALUES (:name, :comment, :password, :date)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
            $stmt->bindParam(':password', $password, PDO::PARAM_STR);
            $stmt->bindParam(':date', $date, PDO::PARAM_STR);
            $stmt->execute();

            echo "投稿を受け付けました<br>";
        }
    } else if (empty($delete_password)) {
        echo "フォームに入力を行ってください<br>";
    }

    // 行を削除
    if (!empty($delete) && is_numeric($delete) && !empty($delete_password)) {
        $delete = intval($delete);

        $found = false;
        $sql = 'SELECT * FROM tbtest';
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll();
        foreach ($results as $row) {
            if ($row['id'] == $delete) {
                // パスワード確認
                if ($delete_password == $row['password']) {
                    $id = $delete;
                    $sql = 'delete from tbtest where id=:id';
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                    $stmt->execute();
                    $found = true;
                    break;
                } else {
                    echo "エラー：パスワードが一致しません。2";
                    break;
                }
            }
        }

        if ($found) {
            echo "指定された投稿を削除しました。";
        } else {
            echo "指定された行は存在しません。";
        }
    }

    // 編集モードに入る前の処理
    if (!empty($edit_number) && is_numeric($edit_number)) {
        $edit_number = intval($edit_number);

        $sql = 'SELECT * FROM tbtest';
        $stmt = $pdo->query($sql);
        $results = $stmt->fetchAll();

        foreach ($results as $row) {
            if ($row['id'] == $edit_number) {
                // パスワード確認
                if ($edit_password == $row['password']) {
                    // 編集対象の内容を取得
                    $edit_name = $row['name'];
                    $edit_comment = $row['comment'];
                    $edit_post_id = $row['id']; // 編集対象の投稿番号を保存
                    break;
                } else {
                    echo "エラー：パスワードが一致しません。3";
                    break;
                }
            }
        }
    }
}
?>

<form action="" method="post">
    <label for="username">User name</label>
    <input type="text" name="name" value="<?php echo $edit_name; ?>">
    <br>
    <label for="comment">Comment</label>
    <input type="text" name="comment" value="<?php echo $edit_comment; ?>">
    <br>
    <label for="password">Password</label>
    <input type="password" name="password" value="<?php echo $edit_password; ?>">
    <br>
    <!-- 編集対象番号の表示 -->
    <?php if (!empty($edit_post_id)): ?>
        <input type="hidden" name="edit_target" value="<?php echo $edit_post_id; ?>">
    <?php endif; ?>
    <!-- 編集モード判定用のhiddenフィールド -->
    <input type="hidden" name="edit_mode" value="<?php echo $edit_post_id ? '1' : '0'; ?>">
    <input type="submit" name="submit" value="<?php echo $edit_post_id ? 'Edit' : 'Submit'; ?>">
    <br><br><br>
    <label for="delete_number">Delete number</label>
    <input type="text" name="delete_number">
    <br>
    <label for="delete_password">Password</label>
    <input type="password" name="delete_password">
    <input type="submit" name="delete" value="Delete">
    <br><br><br>
    <label for="edit_number">Edit number</label>
    <input type="text" name="edit_number">
    <br>
    <label for="edit_password">Password</label>
    <input type="password" name="edit_password">
    <input type="submit" name="edit" value="Edit">
</form>

<?php
// 既存の投稿を表示
$sql = 'SELECT * FROM tbtest';
$stmt = $pdo->query($sql);
$results = $stmt->fetchAll();
foreach ($results as $row) {
    //$rowの中にはテーブルのカラム名が入る
    echo "投稿番号:" .$row['id'] . '<br>';
    echo "投稿者:" .$row['name'] . '<br>';
    echo "コメント:" .$row['comment'] . '<br>';
    echo "投稿日時:" .$row['date'] . '<br>';
    echo "<hr>";
}
?>
</body>
</html>
