# Xserver本番同等検証

この文書はXserver相当のローカル検証手順のみを扱う。仕様の正本は`adlaire-ecosystem.md`。

## 範囲

- ローカル起動
- 確認URL
- 監査コマンド

設計判断やリリース条件は`adlaire-ecosystem.md`に集約する。

## 起動

```sh
docker compose -f Docker/docker-compose.xserver.yml up -d --build
```

確認URL:

```text
http://localhost:8080/
http://localhost:8080/health
```

## 監査

```sh
docker run --rm -v "$PWD:/app" -w /app php:8.3-cli sh scripts/xserver-profile-audit.sh
```

このプロファイルは本番同等検証用であり、前提条件ではない。詳細は`adlaire-ecosystem.md`に集約する。
