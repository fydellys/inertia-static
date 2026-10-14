# nginx + ngx_cache_purge: a cache that can be purged by URL and by prefix (wildcard).
FROM alpine:3.22
RUN apk add --no-cache nginx nginx-mod-http-cache-purge \
    && mkdir -p /var/cache/nginx /run/nginx \
    && ln -sf /dev/stdout /var/log/nginx/access.log && ln -sf /dev/stderr /var/log/nginx/error.log
CMD ["nginx", "-g", "daemon off;"]
