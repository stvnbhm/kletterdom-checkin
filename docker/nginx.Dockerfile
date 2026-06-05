FROM nginx:alpine

RUN apk add --no-cache openssl

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx-entrypoint.sh /docker-entrypoint.d/40-generate-self-signed-cert.sh
RUN chmod +x /docker-entrypoint.d/40-generate-self-signed-cert.sh
