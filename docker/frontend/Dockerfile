# syntax=docker/dockerfile:1

# ---------- Base ----------
FROM node:20-alpine AS base
WORKDIR /app
RUN corepack enable

# ---------- Dev ----------
FROM base AS dev
ENV NODE_ENV=development
CMD ["sh", "-c", "npm install && npm run dev -- --host 0.0.0.0"]

# ---------- Build ----------
FROM base AS build
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm install
COPY frontend/ ./
RUN npm run build

# ---------- Prod (static served by nginx) ----------
FROM nginx:1.27-alpine AS prod
COPY docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
