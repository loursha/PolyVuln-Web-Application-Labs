# ============================================================================
# Dockerfile -- The Antiquary & Relic Co. frontend
#
# Builds the React app with Vite, then serves the static output with nginx,
# which also reverse-proxies /api and /uploads to the backend container
# (see nginx.conf) so the built app works the same way the Vite dev-server
# proxy does locally.
# ============================================================================
FROM node:18-slim AS build
WORKDIR /app
COPY package.json .
RUN npm install
COPY . .
RUN npm run build

FROM nginx:alpine
COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf
EXPOSE 3100
