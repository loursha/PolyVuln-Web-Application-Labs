# ============================================================================
# Dockerfile -- StrideStyle Footwear Co. backend (intentionally vulnerable)
#
# NOTE: This image is built for an ISOLATED LAB NETWORK ONLY. Never publish
# this image to a public registry or expose it to the internet.
# ============================================================================
FROM node:18-slim

WORKDIR /app

# System tool used by the intentionally-vulnerable (allowlist-bounded)
# /api/admin/ping-supplier command-injection demo.
RUN apt-get update && apt-get install -y --no-install-recommends iputils-ping \
    && rm -rf /var/lib/apt/lists/*

COPY package.json .
RUN npm install --production

COPY . .

EXPOSE 4000

CMD ["node", "server.js"]
