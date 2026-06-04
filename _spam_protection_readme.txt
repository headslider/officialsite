REF公式サイト 問い合わせフォーム迷惑メール対策メモ

設置方法:
1. ZIP内の index.html / contact.php / assets フォルダを同じ階層にアップロードしてください。
2. 既存の contact.php がある場合は、必要に応じてバックアップしてから差し替えてください。
3. contact.php 内の送信先は contact@r-flash.com、送信元は noreply@r-flash.com に設定しています。
4. contact.php は contact_guard/ を自動作成し、送信頻度制限と拒否ログを保存します。作成できない場合はサーバーの一時フォルダを使用します。

主な対策:
- JavaScript経由の送信のみ許可
- botが入力しやすい隠し項目 honeypot を追加
- ページ表示後4秒未満の送信を拒否
- 同一IPから1時間3回以上の送信を拒否
- Origin/Refererが別ドメインの場合は拒否
- URLが多い本文、典型スパム語、ヘッダーインジェクションを拒否
- Reply-Toは問い合わせ者、Fromは自社ドメイン固定にして迷惑メール判定を抑制

さらに強くしたい場合:
- Cloudflare Turnstile または Google reCAPTCHA のサイトキー/シークレットキーを取得して追加してください。
- サーバーのアクセスログで POST /contact.php が大量に来ているIPを確認し、.htaccessやサーバー側でブロックしてください。


【2026-05-22 文字化け修正】
contact.php のメール送信処理を、日本語メールで文字化けしにくい ISO-2022-JP 送信に変更しました。件名・本文・From名・Content-Type・Content-Transfer-Encoding を統一しています。
