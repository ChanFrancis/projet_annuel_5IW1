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
# All VITE_* are baked into the bundle at build time, so they must be provided
# as build args. VITE_API_URL: prod uses an EMPTY base (same-origin) — resource
# paths in src/api/resources.ts already start with "/api", so a "/api" base here
# would produce double "/api/api/…" requests.
# Observability (optional — leave empty to disable):
#   VITE_MATOMO_URL / VITE_MATOMO_SITE_ID : self-hosted Matomo analytics
#   VITE_SENTRY_LOADER_URL                : Sentry error tracking (Loader Script)
FROM base AS build
ARG VITE_API_URL=
ARG VITE_MATOMO_URL=
ARG VITE_MATOMO_SITE_ID=
ARG VITE_SENTRY_LOADER_URL=
ENV VITE_API_URL=$VITE_API_URL \
    VITE_MATOMO_URL=$VITE_MATOMO_URL \
    VITE_MATOMO_SITE_ID=$VITE_MATOMO_SITE_ID \
    VITE_SENTRY_LOADER_URL=$VITE_SENTRY_LOADER_URL
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm install
COPY frontend/ ./
RUN npm run build

# ---------- Prod (static served by nginx) ----------
# Listens on :80 only — TLS is terminated by Traefik in front of this container.
FROM nginx:1.27-alpine AS prod
COPY docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
