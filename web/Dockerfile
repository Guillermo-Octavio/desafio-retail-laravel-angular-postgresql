FROM node:24-alpine

WORKDIR /app

# Pre-create the mount point so the named volume inherits node's ownership.
RUN mkdir -p /app/node_modules && chown node:node /app/node_modules

USER node

CMD ["sh", "-c", "[ -d node_modules/@angular ] || npm ci; exec npx ng serve --host 0.0.0.0 --port 4200"]
