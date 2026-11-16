#!/usr/bin/env bash
# Menyiapkan jalur data SAPTO -> Gravitee -> PD UNSRI yang aman: plan API Key
# + pembatasan laju untuk API "API Pangkalan Data". Lihat docs/gravitee.md.
#
# Idempoten: bagian yang sudah ada dilewati, aman dijalankan berulang.
#
#   bash scripts/gravitee-setup.sh --check          hanya MEMBACA, tidak mengubah apa pun
#   bash scripts/gravitee-setup.sh                  buat plan+aplikasi+langganan, deploy,
#                                                   simpan kunci ke .env, lalu uji
#   bash scripts/gravitee-setup.sh --close-keyless  langkah TERAKHIR: tutup plan tanpa kunci
#                                                   (setelah SAPTO terbukti sehat memakai kunci)
#
# Urutannya sengaja tiga tahap terpisah: plan tanpa kunci lama tetap terbuka
# sampai SAPTO terbukti berjalan dengan kunci. Menutupnya lebih dulu akan
# memutus sinkronisasi dan badge status API seketika.
#
# Butuh: curl, jq. Akun admin Gravitee dari env GRAVITEE_ADMIN_USER
# (bawaan: admin) dan GRAVITEE_ADMIN_PASSWORD (bila kosong: dibaca dari file
# /home/sapto/gravitee/.admin-password, lalu ditanya).
set -euo pipefail

MODE="apply"
case "${1:-}" in
    --check) MODE="check" ;;
    --close-keyless) MODE="close" ;;
    "") ;;
    *) echo "Argumen tidak dikenal: $1" >&2; exit 2 ;;
esac

REPO="$(cd "$(dirname "$0")/.." && pwd)"
MGMT="${GRAVITEE_MGMT_URL:-http://127.0.0.1:8083/management/organizations/DEFAULT/environments/DEFAULT}"
GATEWAY_HOST="${GRAVITEE_GATEWAY_URL:-http://127.0.0.1:8077}"
API_NAME="${GRAVITEE_API_NAME:-API Pangkalan Data}"
PLAN_NAME="SAPTO Web App"
APP_NAME="SAPTO Web"
RATE_LIMIT="${SAPTO_GATEWAY_RATE_LIMIT:-30}"   # request per detik
ADMIN_USER="${GRAVITEE_ADMIN_USER:-admin}"

# Password admin: env, lalu file (dibuat saat password bawaan diganti,
# 2026-09-30), lalu tanya.
PASS_FILE="${GRAVITEE_ADMIN_PASSWORD_FILE:-/home/sapto/gravitee/.admin-password}"
if [ -z "${GRAVITEE_ADMIN_PASSWORD:-}" ] && [ -r "$PASS_FILE" ]; then
    GRAVITEE_ADMIN_PASSWORD="$(head -n1 "$PASS_FILE")"
fi
if [ -z "${GRAVITEE_ADMIN_PASSWORD:-}" ]; then
    read -r -s -p "Password admin Gravitee ($ADMIN_USER): " GRAVITEE_ADMIN_PASSWORD
    echo
fi

api() { # api METHOD PATH [json-body]
    local method="$1" path="$2" body="${3:-}"
    if [ -n "$body" ]; then
        curl -sS --max-time 60 -u "$ADMIN_USER:$GRAVITEE_ADMIN_PASSWORD" -X "$method" \
            -H 'Content-Type: application/json' -d "$body" "$MGMT$path"
    else
        curl -sS --max-time 60 -u "$ADMIN_USER:$GRAVITEE_ADMIN_PASSWORD" -X "$method" "$MGMT$path"
    fi
}

say() { printf '%s\n' "$*"; }
step() { printf '\n== %s\n' "$*"; }
die() { printf 'GAGAL: %s\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------- temukan API
step "Mencari API \"$API_NAME\""
APIS="$(api GET /apis)" || die "Management API tidak terjangkau di $MGMT"
echo "$APIS" | jq -e 'type == "array"' >/dev/null 2>&1 || die "Login ditolak atau respons tak terduga: $(echo "$APIS" | head -c 200)"
API_ID="$(echo "$APIS" | jq -r --arg n "$API_NAME" '[.[] | select(.name == $n)][0].id // empty')"
[ -n "$API_ID" ] || die "API \"$API_NAME\" tidak ditemukan."
say "API id: $API_ID"

PLANS="$(api GET "/apis/$API_ID/plans?status=staging,published,closed,deprecated")"
KEYLESS_ID="$(echo "$PLANS" | jq -r '[.[] | select(.security == "KEY_LESS" and .status == "PUBLISHED")][0].id // empty')"
PLAN_ID="$(echo "$PLANS" | jq -r --arg n "$PLAN_NAME" '[.[] | select(.name == $n and .status != "CLOSED")][0].id // empty')"
PLAN_STATUS="$(echo "$PLANS" | jq -r --arg n "$PLAN_NAME" '[.[] | select(.name == $n and .status != "CLOSED")][0].status // empty')"

APPS="$(api GET /applications)"
APP_ID="$(echo "$APPS" | jq -r --arg n "$APP_NAME" '[.[] | select(.name == $n)][0].id // empty')"

say "Plan tanpa kunci (KEY_LESS) terbuka : ${KEYLESS_ID:-tidak ada}"
say "Plan \"$PLAN_NAME\"                    : ${PLAN_ID:-belum ada} ${PLAN_STATUS:+($PLAN_STATUS)}"
say "Aplikasi \"$APP_NAME\"                    : ${APP_ID:-belum ada}"

if [ "$MODE" = "check" ]; then
    step "Mode --check: tidak ada yang diubah."
    [ -n "$PLAN_ID" ] || say "Akan dibuat: plan API_KEY \"$PLAN_NAME\" (batas $RATE_LIMIT request/detik)."
    [ -n "$APP_ID" ] || say "Akan dibuat: aplikasi \"$APP_NAME\" + langganan ke plan itu."
    exit 0
fi

# ---------------------------------------------------------------- tutup keyless
if [ "$MODE" = "close" ]; then
    KEY="$(grep -E '^SAPTO_GATEWAY_API_KEY=' "$REPO/.env" | tail -1 | cut -d= -f2- || true)"
    [ -n "$KEY" ] || die "SAPTO_GATEWAY_API_KEY belum ada di .env. Jalankan dulu tanpa argumen."
    [ -n "$KEYLESS_ID" ] || { say "Plan tanpa kunci sudah tidak ada. Selesai."; exit 0; }

    step "Memastikan kunci berfungsi sebelum menutup plan tanpa kunci"
    CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 -H "X-Gravitee-Api-Key: $KEY" "$GATEWAY_HOST/sapto-api/up")"
    [ "$CODE" != "401" ] && [ "$CODE" != "000" ] || die "Gateway menolak kunci di .env (HTTP $CODE). Plan tanpa kunci TIDAK ditutup."
    say "Kunci diterima gateway (HTTP $CODE)."

    step "Menutup plan tanpa kunci"
    CLOSE_RES="$(api POST "/apis/$API_ID/plans/$KEYLESS_ID/_close")"
    CLOSED="$(echo "$CLOSE_RES" | jq -r '.status // empty' 2>/dev/null || true)"
    [ "$CLOSED" = "CLOSED" ] || die "Plan tanpa kunci gagal ditutup: $(echo "$CLOSE_RES" | head -c 300)"
    api POST "/apis/$API_ID/deploy" '{"deploymentLabel":"Tutup plan tanpa kunci"}' >/dev/null

    # Gateway butuh beberapa detik sampai puluhan detik menerapkan deploy.
    say "Menunggu gateway menerapkan (maks 2 menit)..."
    NOKEY="000"
    for _ in $(seq 1 40); do
        NOKEY="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$GATEWAY_HOST/sapto-api/up")"
        [ "$NOKEY" = "401" ] && break
        sleep 3
    done
    say "Tanpa kunci sekarang: HTTP $NOKEY (harus 401)."
    [ "$NOKEY" = "401" ] || die "Gateway belum menolak request tanpa kunci. Periksa deploy di Management UI."
    exit 0
fi

# ---------------------------------------------------------------- plan
if [ -z "$PLAN_ID" ]; then
    step "Membuat plan API Key \"$PLAN_NAME\" (batas $RATE_LIMIT request/detik)"
    BODY="$(jq -n --arg name "$PLAN_NAME" --argjson limit "$RATE_LIMIT" '{
        name: $name,
        description: "Akses aplikasi SAPTO (web, sinkronisasi, health check). Wajib header X-Gravitee-Api-Key. Dibatasi supaya server PD UNSRI tidak kebanjiran.",
        validation: "AUTO", security: "API_KEY", type: "API", status: "STAGING",
        characteristics: ["internal"],
        flows: [{
            name: "Pembatasan laju", enabled: true, consumers: [], methods: [], condition: "",
            "path-operator": {path: "/", operator: "STARTS_WITH"},
            pre: [{name: "Rate Limiting", description: "Maks per detik per langganan", enabled: true,
                   policy: "rate-limit",
                   configuration: {async: false, addHeaders: true,
                                   rate: {periodTime: 1, limit: $limit, periodTimeUnit: "SECONDS", key: ""}}}],
            post: []
        }]
    }')"
    PLAN_RES="$(api POST "/apis/$API_ID/plans" "$BODY")"
    PLAN_ID="$(echo "$PLAN_RES" | jq -r '.id // empty')"
    [ -n "$PLAN_ID" ] || die "Plan gagal dibuat: $(echo "$PLAN_RES" | head -c 300)"
    PLAN_STATUS="STAGING"
fi
if [ "$PLAN_STATUS" = "STAGING" ]; then
    step "Mempublikasikan plan"
    api POST "/apis/$API_ID/plans/$PLAN_ID/_publish" >/dev/null
fi

# ---------------------------------------------------------------- aplikasi + langganan
if [ -z "$APP_ID" ]; then
    step "Membuat aplikasi \"$APP_NAME\""
    APP_RES="$(api POST /applications "$(jq -n --arg n "$APP_NAME" '{name: $n, description: "Aplikasi SAPTO (sapto-prod) yang mengambil data PD UNSRI lewat gateway ini.", settings: {app: {type: "backend"}}}')")"
    APP_ID="$(echo "$APP_RES" | jq -r '.id // empty')"
    [ -n "$APP_ID" ] || die "Aplikasi gagal dibuat: $(echo "$APP_RES" | head -c 300)"
fi

SUBS="$(api GET "/apis/$API_ID/subscriptions?plan=$PLAN_ID&application=$APP_ID&status=ACCEPTED,PENDING")"
SUB_ID="$(echo "$SUBS" | jq -r '(.data // .)[0].id // empty' 2>/dev/null || true)"
if [ -z "$SUB_ID" ]; then
    step "Membuat langganan aplikasi -> plan"
    SUB_RES="$(api POST "/applications/$APP_ID/subscriptions?plan=$PLAN_ID")"
    SUB_ID="$(echo "$SUB_RES" | jq -r '.id // empty')"
    [ -n "$SUB_ID" ] || die "Langganan gagal dibuat: $(echo "$SUB_RES" | head -c 300)"
fi

step "Mengambil kunci API"
KEYS="$(api GET "/apis/$API_ID/subscriptions/$SUB_ID/apikeys")"
KEY="$(echo "$KEYS" | jq -r '[.[] | select(.revoked != true and .expired != true)][0].key // empty' 2>/dev/null || true)"
[ -n "$KEY" ] || KEY="$(api GET "/apis/$API_ID/subscriptions/$SUB_ID" | jq -r '.keys[0].key // empty' 2>/dev/null || true)"
[ -n "$KEY" ] || die "Kunci API tidak ditemukan untuk langganan $SUB_ID. Ambil manual di Management UI (Applications -> $APP_NAME -> Subscriptions)."

# ---------------------------------------------------------------- deploy + simpan
step "Deploy konfigurasi ke gateway"
api POST "/apis/$API_ID/deploy" '{"deploymentLabel":"Plan API Key SAPTO + rate limit"}' >/dev/null
sleep 6

step "Menyimpan kunci ke .env (SAPTO_GATEWAY_API_KEY)"
ENV_FILE="$REPO/.env"
if grep -q '^SAPTO_GATEWAY_API_KEY=' "$ENV_FILE"; then
    tmp="$(mktemp)"; sed "s|^SAPTO_GATEWAY_API_KEY=.*|SAPTO_GATEWAY_API_KEY=$KEY|" "$ENV_FILE" >"$tmp" && cat "$tmp" >"$ENV_FILE"; rm -f "$tmp"
else
    printf '\n# Kunci langganan plan "%s" di Gravitee (lihat docs/gravitee.md)\nSAPTO_GATEWAY_API_KEY=%s\n' "$PLAN_NAME" "$KEY" >>"$ENV_FILE"
fi
say "Tersimpan. (nilai kunci tidak ditampilkan)"

# ---------------------------------------------------------------- uji
step "Uji jalur gateway"
WITH="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 -H "X-Gravitee-Api-Key: $KEY" "$GATEWAY_HOST/sapto-api/up")"
WITHOUT="$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$GATEWAY_HOST/sapto-api/up")"
say "Dengan kunci : HTTP $WITH  (bukan 401 = kunci diterima)"
say "Tanpa kunci  : HTTP $WITHOUT (masih terbuka selama plan tanpa kunci belum ditutup)"
[ "$WITH" != "401" ] || die "Gateway menolak kunci baru. Periksa langganan di Management UI."

cat <<MSG

Selesai. SAPTO membaca kunci dari .env pada request berikutnya (tanpa restart).
1. Cek badge "Status API PD UNSRI" di SAPTO tetap Online selama beberapa menit.
2. Bila sehat, tutup plan tanpa kunci:  bash scripts/gravitee-setup.sh --close-keyless
MSG
