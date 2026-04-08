# =====================================================
#  EduGlass School ERP - Server Dockerfile
#  Production-ready Node.js + Prisma + MySQL
# =====================================================

FROM node:20-alpine AS base

# Install dependencies for Prisma
RUN apk add --no-cache openssl

WORKDIR /app

# Copy package files
COPY package*.json ./
COPY prisma ./prisma/

# Install dependencies
RUN npm ci --only=production && npm cache clean --force

# Copy source code
COPY . .

# Generate Prisma Client
RUN npx prisma generate

# =====================================================
FROM node:20-alpine AS builder

WORKDIR /app

COPY package*.json ./
COPY prisma ./prisma/

# Install all dependencies (including dev)
RUN npm ci && npm cache clean --force

COPY . .

# Generate Prisma Client
RUN npx prisma generate

# =====================================================
FROM node:20-alpine AS production

# Security: Create non-root user
RUN addgroup -g 1001 -S nodejs && \
    adduser -S nodejs -u 1001

WORKDIR /app

# Copy from builder
COPY --from=builder /app/package*.json ./
COPY --from=builder /app/prisma ./prisma/
COPY --from=builder /app/server.js ./
COPY --from=builder /app/config ./config/
COPY --from=builder /app/middleware ./middleware/
COPY --from=builder /app/models ./models/
COPY --from=builder /app/routes ./routes/
COPY --from=builder /app/services ./services/
COPY --from=builder /app/utils ./utils/
COPY --from=builder /app/ai ./ai/
COPY --from=builder /app/scripts ./scripts/
COPY --from=builder /app/seed.js ./

# Copy production dependencies from base
COPY --from=base /app/node_modules ./node_modules

# Set environment to production
ENV NODE_ENV=production

# Expose port
EXPOSE 5000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
  CMD wget --no-verbose --tries=1 --spider http://localhost:5000/api/health || exit 1

# Switch to non-root user
USER nodejs

# Start the application
CMD ["node", "server.js"]
