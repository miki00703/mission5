# PHP Bulletin Board

PHPとMySQLで実装した、投稿・編集・削除機能を持つシンプルな掲示板です。学習課題として作成した実装を、公開用にセキュリティと可搬性を見直しています。

## 主な機能

- 名前・コメント・操作用パスワードを指定した投稿
- 投稿番号とパスワードによる編集・削除
- PDOのプリペアドステートメントによるデータベース操作
- パスワードのハッシュ保存
- CSRFトークンによるPOST操作の保護
- 出力時エスケープによるXSS対策
- Post/Redirect/Getによるフォーム再送信の防止
- 環境変数によるデータベース認証情報の分離

## 動作環境

- PHP 8.1以上
- MySQL 8.0以上、または互換性のあるMariaDB
- PDO MySQL拡張
- PHPセッションが利用できるWebサーバー

## セットアップ

1. MySQLに掲示板用データベースを作成します。
2. Webサーバー側で次の環境変数を設定します。

```text
DB_DSN=mysql:host=127.0.0.1;dbname=bulletin_board;charset=utf8mb4
DB_USER=your_database_user
DB_PASSWORD=your_database_password
```

3. `mission5-1.php`をWebサーバーの公開ディレクトリへ配置します。
4. ブラウザからアクセスします。初回アクセス時に`tbtest`テーブルが自動作成されます。

ローカル開発用の設定例は`.env.example`を参照してください。この実装自体は`.env`を自動読込しないため、Apache、Nginx、PHP-FPM、Dockerなどの実行環境から環境変数を渡してください。

## セキュリティ上の注意

このリポジトリは学習用の最小構成です。本番運用では、HTTPS、レート制限、監査ログ、セッションCookie属性、Content Security Policy、入力文字数制限、管理者機能などを追加してください。

旧版で作成済みの投稿は平文パスワードの可能性があります。互換性のため認証は可能ですが、編集時に新しいパスワードを設定してハッシュ化することを推奨します。

## ファイル構成

```text
mission5-1.php  掲示板本体
.env.example    環境変数の設定例
.gitignore      Git管理から除外するローカル設定
SECURITY.md     脆弱性報告方針
```

## 公開目的

PHP、PDO、MySQL、フォーム処理、CRUD、基本的なWebセキュリティを学んだ成果物として公開しています。
