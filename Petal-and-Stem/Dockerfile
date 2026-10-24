FROM node:20-bookworm-slim
# ping + iproute2 are needed by the hub diagnostics feature
RUN apt-get update && apt-get install -y --no-install-recommends iputils-ping iproute2 && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm install --omit=dev --no-audit --no-fund
COPY . .
RUN mkdir -p logs storage/invoices && chown -R node:node /app/logs /app/storage
USER node
EXPOSE 3000
CMD ["node", "server.js"]
