# officialsite デプロイ情報

Real Emotion Factory 公式サイト `headslider/officialsite` のデプロイ・本番反映に関する引継ぎ資料です。  
このファイルは **GitHub上で管理する運用メモ** であり、**本番サーバーの公開ディレクトリにはアップロードしません**。

---

## 1. GitHubリポジトリ

```text
headslider/officialsite
```

| 項目 | 内容 |
|---|---|
| リポジトリ名 | `officialsite` |
| 所有者 | `headslider` |
| ブランチ | `main` |
| 用途 | Real Emotion Factory 公式サイトのソース管理 |
| ローカル作業場所 | `C:\Users\owner\Documents\officialsite` |

---

## 2. 本番サーバー情報

```text
s59.coreserver.jp
```

| 項目 | 内容 |
|---|---|
| サーバー | CORESERVER |
| ホスト | `s59.coreserver.jp` |
| SSHユーザー | `ref` |
| SSH接続 | `ssh ref@s59.coreserver.jp` |
| ホームディレクトリ | `/virtual/ref` |

---

## 3. 本番反映先

公式サイトの本番反映先は以下です。

```text
/virtual/ref/public_html/www.realemotionfactory.com/
```

簡略表記する場合：

```text
ref/public_html/www.realemotionfactory.com/
```

ただし、正確な絶対パスは必ず以下を使います。

```text
/virtual/ref/public_html/www.realemotionfactory.com/
```

---

## 4. 本番パス確認済みコマンド

SSHログイン後、以下のコマンドで確認済みです。

```bash
pwd
# /virtual/ref

ls -la ~/public_html
# realemotionfactory.com
# www.realemotionfactory.com

ls -la ~/public_html/www.realemotionfactory.com
# index.html
# contact.php
# assets/
# contact_guard/
# baseball/
# baseball_test/
```

---

## 5. 本番ディレクトリ内の重要フォルダ

```text
/virtual/ref/public_html/www.realemotionfactory.com/
├─ index.html
├─ contact.php
├─ assets/
├─ contact_guard/
├─ baseball/
└─ baseball_test/
```

| パス | 用途 | 注意 |
|---|---|---|
| `index.html` | REF公式サイトトップ | 反映対象 |
| `contact.php` | 問い合わせフォーム送信処理 | 反映対象 |
| `assets/` | 公式サイト画像 | 反映対象 |
| `contact_guard/` | 問い合わせフォーム防御・ログ | 必要ファイルのみ反映 |
| `baseball/` | 少年野球シミュレーター本番 | officialsite更新時は触らない |
| `baseball_test/` | 少年野球シミュレーターテスト | officialsite更新時は触らない |

---

## 6. officialsite の反映対象

本番反映してよいものは以下です。

```text
index.html
contact.php
assets/
contact_guard/.htaccess
contact_guard/blocked_emails.txt
contact_guard/blocked_phrases.txt
```

### 補足

`contact_guard/blocked_emails.txt` と `contact_guard/blocked_phrases.txt` は、スパムブロック用の手動管理ファイルです。  
本番側で直接更新している場合があるため、上書き前に本番側との差分を確認してください。

---

## 7. 本番反映してはいけないもの

以下は本番サーバーへアップロードしません。

```text
DEPLOY.md
README.md
HANDOVER.md
officialsite_handover.md
_spam_protection_readme.txt
_future_yakyu_yarouze_work_card_snippet.html
assets_manifest.json
.gitignore
.gitattributes
.git/
*.zip
*.bak
*.tmp
desktop.ini
```

以下はGitHubにも本番反映にも含めません。

```text
contact_guard/*.log
contact_guard/*.json
contact_guard/*.lock
contact_guard/ref_contact_reject.log
contact_guard/rate_*.json
contact_guard/dup_*.json
```

理由：

- 問い合わせフォームの運用ログ・レート制限・重複判定ファイルであるため
- 本番環境で自動生成されるため
- 個人情報・IP・送信履歴に近い情報を含む可能性があるため
- GitHubや公開ディレクトリへ置くべきではないため

---

## 8. 絶対に触らないディレクトリ

officialsite の更新作業では、以下を上書き・削除・同期対象に含めないでください。

```text
/virtual/ref/public_html/www.realemotionfactory.com/baseball/
/virtual/ref/public_html/www.realemotionfactory.com/baseball_test/
```

理由：

- `/baseball/` は少年野球シミュレーター「野球やろうぜ！」の本番環境
- `/baseball_test/` は少年野球シミュレーターのテスト環境
- officialsiteリポジトリとは別管理の重要アプリ領域

特に `rsync --delete` を使う場合、対象ディレクトリを誤ると `baseball/` や `baseball_test/` を削除する危険があります。

---

## 9. 本番反映前の必須確認

本番反映前に必ず確認します。

### Git確認

```bash
git status
git diff
```

確認すること：

- `contact_guard/*.json` が含まれていない
- `contact_guard/*.log` が含まれていない
- `desktop.ini` が含まれていない
- `.zip` が含まれていない
- `DEPLOY.md` などの運用メモを本番反映対象にしていない

### 表示確認

- トップページが正常表示される
- スマホ表示で崩れない
- ハンバーガーメニューが動く
- 制作実績画像が表示される
- 制作実績画像の上部が切れていない
- 「野球やろうぜ！」制作実績が非表示のまま

### 問い合わせ確認

- 問い合わせフォームが送信できる
- 日本語メールが文字化けしない
- スパム対策が無効化されていない
- `contact_guard/` が外部から直接閲覧できない

---

## 10. 問い合わせフォームの重要仕様

`contact.php` には、以下の対策が実装されています。

| 対策 | 内容 |
|---|---|
| Honeypot | botが隠し入力欄に入力した場合に拒否 |
| 送信速度チェック | ページ表示直後の自動送信を拒否 |
| JavaScript通過チェック | フォーム画面を通らない直接POSTを拒否しやすくする |
| IP制限 | 同一IPからの連続送信を制限 |
| URL過多チェック | 本文内URLが多すぎる送信を拒否 |
| スパム語句チェック | 典型的なスパム文言を拒否 |
| 重複本文ブロック | 同じ問い合わせ本文を一定期間ブロック |
| 同一入力内容ブロック | 名前・会社・メール・電話・本文が同じ送信をブロック |
| 手動メールブロック | `blocked_emails.txt` に登録されたメールを拒否 |
| 手動フレーズブロック | `blocked_phrases.txt` に登録された文言を拒否 |
| 日本語メール文字化け対策 | 件名・本文・ヘッダーの文字コード対策 |
| ヘッダーインジェクション対策 | メールヘッダー悪用を防止 |

`contact.php` を修正する場合、上記を壊さないでください。

---

## 11. contact_guard の管理ルール

### GitHubで管理するファイル

```text
contact_guard/.htaccess
contact_guard/blocked_emails.txt
contact_guard/blocked_phrases.txt
```

### GitHubで管理しないファイル

```text
contact_guard/*.log
contact_guard/*.json
contact_guard/*.lock
contact_guard/rate_*.json
contact_guard/dup_*.json
contact_guard/ref_contact_reject.log
```

### .gitignore 必須設定

```gitignore
# OS
.DS_Store
Thumbs.db
desktop.ini

# Editor
.vscode/
.idea/

# Archives / backups
*.zip
*.bak
*.tmp

# Contact form runtime data
contact_guard/*.log
contact_guard/*.json
contact_guard/*.lock
contact_guard/ref_contact_reject.log
contact_guard/rate_*.json
contact_guard/dup_*.json
```

---

## 12. Git運用

### 通常の作業開始

```bat
cd C:\Users\owner\Documents\officialsite
git pull
```

### 修正後

```bat
git status
git add .
git status
git commit -m "修正内容"
git push
```

### 間違ってログやJSONをステージした場合

```bat
git rm --cached contact_guard/*.json
git rm --cached contact_guard/*.log
```

または、まとめて外して必要な3ファイルだけ戻します。

```bat
git rm --cached -r contact_guard
git add contact_guard/.htaccess
git add contact_guard/blocked_emails.txt
git add contact_guard/blocked_phrases.txt
```

---

## 13. 改行コード

Windows環境で作業するため、改行コードの警告が出ることがあります。  
`.gitattributes` でLF統一する方針です。

```gitattributes
* text=auto eol=lf

*.html text eol=lf
*.php text eol=lf
*.css text eol=lf
*.js text eol=lf
*.json text eol=lf
*.txt text eol=lf
*.md text eol=lf
*.htaccess text eol=lf

*.png binary
*.jpg binary
*.jpeg binary
*.gif binary
*.webp binary
*.ico binary
```

以下のような警告は、LFへ統一される通知なので基本的に問題ありません。

```text
CRLF will be replaced by LF the next time Git touches it
```

---

## 14. 現在のサイト仕様メモ

### 画像

- 画像はBase64埋め込み禁止
- すべて `assets/` 配下の外部画像として管理
- 制作実績画像は上が切れないようにする
- 解像度を不用意に落とさない

推奨CSS方針：

```css
object-fit: contain;
object-position: center top;
```

### 制作実績

現在、少年野球シミュレーター「野球やろうぜ！」の制作実績は非表示です。

- 勝手に公開しない
- 後日公開タイミングで表示に戻す
- 復帰用スニペットは開発メモとして保持可能
- 本番公開する場合は、リンク・画像・説明文・スライド動作を再検証する

### GPT表記

- `GPT-4o` 表記は使わない
- 現在は `GPT-5.5` 表記
- GPT関連表記を変更する場合は、必ず最新の公式情報を確認する

---

## 15. Codexへの指示例

Codexに修正作業を依頼する場合は、以下の内容を最初に渡してください。

```text
このリポジトリは Real Emotion Factory 公式サイト `headslider/officialsite` です。

本番反映先は以下です。
/virtual/ref/public_html/www.realemotionfactory.com/

ただし、以下は絶対に触らないでください。
/virtual/ref/public_html/www.realemotionfactory.com/baseball/
/virtual/ref/public_html/www.realemotionfactory.com/baseball_test/

重要ルール：
- contact.php のスパム対策と日本語メール文字化け対策を壊さない
- contact_guard/*.json、contact_guard/*.log、*.lock はコミットしない
- contact_guard/.htaccess、blocked_emails.txt、blocked_phrases.txt は保持する
- 画像はBase64埋め込み禁止。assets/配下に外部化する
- 「野球やろうぜ！」制作実績は現在非表示。公開タイミングまでは表示しない
- GPT-4o 表記を戻さない
- 制作実績画像は上が切れないようにする
- DEPLOY.md、README.md、HANDOVER.md は本番サーバーへアップロードしない
- 本番反映前に必ず差分確認と表示確認を行う
```

---

## 16. 本番反映の基本方針

手動で本番反映する場合は、反映対象を限定します。

反映対象：

```text
index.html
contact.php
assets/
contact_guard/.htaccess
contact_guard/blocked_emails.txt
contact_guard/blocked_phrases.txt
```

反映しない：

```text
DEPLOY.md
README.md
HANDOVER.md
.git/
.gitignore
.gitattributes
contact_guard/*.json
contact_guard/*.log
contact_guard/*.lock
baseball/
baseball_test/
```

---

## 17. 最重要注意

officialsite の本番反映で、以下を絶対に削除・上書きしないでください。

```text
/virtual/ref/public_html/www.realemotionfactory.com/baseball/
/virtual/ref/public_html/www.realemotionfactory.com/baseball_test/
```

以上。
