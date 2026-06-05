#!/bin/sh
set -eu

CERT_DIR=/etc/nginx/ssl
CERT_FILE="$CERT_DIR/nginx.crt"
KEY_FILE="$CERT_DIR/nginx.key"
SUBJECT_ALT_NAME="${SSL_SUBJECT_ALT_NAME:-DNS:kletterdom.local}"

mkdir -p "$CERT_DIR"

if [ ! -s "$CERT_FILE" ] || [ ! -s "$KEY_FILE" ]; then
    openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
        -keyout "$KEY_FILE" \
        -out "$CERT_FILE" \
        -subj "/CN=kletterdom.local" \
        -addext "subjectAltName=$SUBJECT_ALT_NAME"
fi
