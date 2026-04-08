# 🚀 EduGlass School ERP - DEPLOYMENT GUIDE

> **Complete guide for deploying to production - Docker, VPS, Cloud Platforms**

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Option 1: Docker Deployment (Recommended)](#option-1-docker-deployment-recommended)
3. [Option 2: VPS Deployment (DigitalOcean, AWS EC2, etc.)](#option-2-vps-deployment-digitalocean-aws-ec2-etc)
4. [Option 3: Platform-as-a-Service (Railway, Render, Heroku)](#option-3-platform-as-a-service-railway-render-heroku)
5. [Option 4: Separate Frontend + Backend Deployment](#option-4-separate-frontend--backend-deployment)
6. [Post-Deployment Checklist](#post-deployment-checklist)
7. [SSL/HTTPS Setup](#sslhttps-setup)
8. [Backup Strategy](#backup-strategy)
9. [Monitoring & Logging](#monitoring--logging)
10. [Troubleshooting](#troubleshooting)

---

## 🔧 Prerequisites

### Required Software
- **Git** - For cloning the repository
- **Docker & Docker Compose** (for Docker deployment) - [Install Docker](https://docs.docker.com/get-docker/)
- **Node.js 18+** (for manual deployment) - [Install Node.js](https://nodejs.org/)
- **MySQL 8.0+** (for manual deployment) - [Install MySQL](https://dev.mysql.com/downloads/)

### Required Infrastructure
- **Server:** Minimum 2GB RAM, 2 CPU cores (4GB+ recommended)
- **Database:** MySQL 8.0+ (local or managed)
- **Domain:** Optional but recommended for production
- **SSL Certificate:** Let's Encrypt (free) or commercial

---

## 🐳 Option 1: Docker Deployment (Recommended)

**Best for:** Quick setup, consistent environments, easy scaling

### Step 1: Clone the Repository

```bash
git clone <your-repo-url> school-erp
cd school-erp
```

### Step 2: Create Environment File

```bash
cp .env.example .env
```

Edit `.env` with your production values:

```env
# MySQL Configuration
MYSQL_ROOT_PASSWORD=your-secure-root-password
MYSQL_PASSWORD=your-database-user-password

# Backend Configuration
JWT_SECRET=generate-a-long-random-string-here-use-openssl-rand-hex-48
SEED_SUPERADMIN_PASSWORD=your-admin-password

# Frontend Configuration
FRONTEND_URL=http://your-domain.com
REACT_APP_API_URL=http://your-domain.com:5000/api
SCHOOL_NAME=Your School Name

# Optional: SMS (Twilio)
TWILIO_ACCOUNT_SID=your-twilio-sid
TWILIO_AUTH_TOKEN=your-twilio-token
TWILIO_PHONE_NUMBER=+1234567890
```

### Step 3: Generate JWT Secret

```bash
# Linux/macOS
openssl rand -hex 48

# Windows (PowerShell)
-join ((1..48) | ForEach-Object { "{0:x}" -f (Get-Random -Maximum 16) })
```

### Step 4: Build and Start

```bash
# Build and start all services
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f server
docker-compose logs -f client
docker-compose logs -f mysql
```

### Step 5: Initialize Database

```bash
# Wait for MySQL to be ready (takes ~30 seconds)
docker-compose exec mysql mysqladmin ping -h localhost --wait

# Run Prisma migrations
docker-compose exec server npx prisma migrate deploy

# Seed the database (create admin account)
docker-compose exec server node seed.js
```

### Step 6: Access the Application

- **Frontend:** http://localhost (or http://your-domain.com)
- **Backend API:** http://localhost:5000
- **Login:** admin@school.com / (your SEED_SUPERADMIN_PASSWORD)

### Docker Management Commands

```bash
# Stop all services
docker-compose down

# Stop and remove volumes (WARNING: deletes all data)
docker-compose down -v

# Restart a specific service
docker-compose restart server

# View logs
docker-compose logs -f server

# Run database backup
docker-compose exec server node scripts/backup-db.js

# Update the application
git pull
docker-compose down
docker-compose up -d --build
docker-compose exec server npx prisma migrate deploy
```

---

## 💻 Option 2: VPS Deployment (DigitalOcean, AWS EC2, etc.)

**Best for:** Full control, custom configurations, existing infrastructure

### Step 1: Set Up Server

#### A. Create a VPS Instance
- **DigitalOcean:** Ubuntu 22.04 LTS, 4GB RAM, 2 CPU cores
- **AWS EC2:** t3.medium, Ubuntu 22.04 LTS
- **Linode:** 4GB plan, Ubuntu 22.04 LTS

#### B. Connect to Server

```bash
ssh root@your-server-ip
```

#### C. Install Dependencies

```bash
# Update system
apt update && apt upgrade -y

# Install Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs

# Install MySQL
apt install -y mysql-server

# Install Nginx
apt install -y nginx

# Install PM2 globally
npm install -g pm2

# Install Git
apt install -y git
```

### Step 2: Configure MySQL

```bash
# Start MySQL service
systemctl start mysql
systemctl enable mysql

# Secure MySQL installation
mysql_secure_installation

# Login to MySQL
mysql -u root -p
```

```sql
-- Create database and user
CREATE DATABASE school_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'school_erp_user'@'localhost' IDENTIFIED BY 'your-secure-password';
GRANT ALL PRIVILEGES ON school_erp.* TO 'school_erp_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 3: Deploy Application

```bash
# Create app directory
mkdir -p /var/www/school-erp
cd /var/www/school-erp

# Clone repository (or upload via SCP/SFTP)
git clone <your-repo-url> .
```

### Step 4: Configure Backend

```bash
cd /var/www/school-erp/server

# Install dependencies
npm install --production

# Create .env file
nano .env
```

Add this to `/var/www/school-erp/server/.env`:

```env
NODE_ENV=production
PORT=5000
DATABASE_URL=mysql://school_erp_user:your-secure-password@localhost:3306/school_erp
JWT_SECRET=your-long-random-secret-string-here
JWT_EXPIRES_IN=7d
FRONTEND_URL=http://your-domain.com
SEED_SUPERADMIN_PASSWORD=your-admin-password
SCHOOL_NAME=Your School Name
LOG_LEVEL=info
```

### Step 5: Configure Frontend

```bash
cd /var/www/school-erp/client

# Install dependencies
npm install

# Create .env file
nano .env
```

Add this to `/var/www/school-erp/client/.env`:

```env
REACT_APP_API_URL=http://your-domain.com/api
REACT_APP_SCHOOL_NAME=Your School Name
```

### Step 6: Build Frontend

```bash
cd /var/www/school-erp/client
npm run build
```

### Step 7: Initialize Database

```bash
cd /var/www/school-erp/server

# Run Prisma migrations
npx prisma migrate deploy

# Seed database
node seed.js
```

### Step 8: Setup PM2

```bash
cd /var/www/school-erp/server

# Start with PM2
pm2 start ecosystem.config.js

# Save PM2 process list
pm2 save

# Setup PM2 to start on boot
pm2 startup systemd
```

### Step 9: Configure Nginx

Create Nginx configuration:

```bash
nano /etc/nginx/sites-available/school-erp
```

Add this configuration:

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;

    # Frontend (React build)
    location / {
        root /var/www/school-erp/client/build;
        try_files $uri $uri/ /index.html;
        
        # Cache static assets
        location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
        }
    }

    # Backend API proxy
    location /api/ {
        proxy_pass http://localhost:5000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
        
        # Increase upload limit
        client_max_body_size 50M;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;
}
```

Enable the site:

```bash
# Create symbolic link
ln -s /etc/nginx/sites-available/school-erp /etc/nginx/sites-enabled/

# Remove default site
rm /etc/nginx/sites-enabled/default

# Test Nginx configuration
nginx -t

# Restart Nginx
systemctl restart nginx
systemctl enable nginx
```

### Step 10: Setup Firewall

```bash
# Install UFW
apt install -y ufw

# Allow SSH, HTTP, HTTPS
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable

# Check status
ufw status
```

---

## ☁️ Option 3: Platform-as-a-Service (Railway, Render, Heroku)

**Best for:** Easy deployment, managed infrastructure, automatic scaling

### Deploy to Railway

1. **Create Railway Account:** [railway.app](https://railway.app)

2. **Connect Repository:**
   - Click "New Project" → "Deploy from GitHub repo"
   - Select your school-erp repository

3. **Add MySQL Database:**
   - Click "New" → "Database" → "Add MySQL"
   - Copy the `DATABASE_URL`

4. **Configure Server:**
   - Add environment variables in Railway dashboard:
     ```
     DATABASE_URL=mysql://...
     JWT_SECRET=your-secret
     SEED_SUPERADMIN_PASSWORD=admin123
     NODE_ENV=production
     ```
   - Set build command: `npx prisma migrate deploy`
   - Set start command: `node server.js`

5. **Deploy Client:**
   - Add another service for `/client` directory
   - Set build command: `npm run build`
   - Set output directory: `build`

6. **Access Application:**
   - Railway provides a public URL automatically

### Deploy to Render

1. **Create Render Account:** [render.com](https://render.com)

2. **Deploy Backend:**
   - New Web Service → Connect repository
   - Root Directory: `server`
   - Build Command: `npm install && npx prisma migrate deploy`
   - Start Command: `node server.js`
   - Add MySQL database via Render dashboard
   - Set environment variables

3. **Deploy Frontend:**
   - New Static Site → Connect repository
   - Root Directory: `client`
   - Build Command: `npm install && npm run build`
   - Publish Directory: `build`
   - Set environment variable: `REACT_APP_API_URL=https://your-backend.onrender.com/api`

### Deploy to Heroku

1. **Install Heroku CLI:** [devcenter.heroku.com/heroku-cli](https://devcenter.heroku.com/articles/heroku-cli)

2. **Deploy Backend:**

```bash
cd server
heroku login
heroku create school-erp-api
heroku addons:create cleardb:ignite  # MySQL addon
heroku config:set NODE_ENV=production
heroku config:set JWT_SECRET=your-secret
heroku config:set SEED_SUPERADMIN_PASSWORD=admin123
git push heroku main
```

3. **Deploy Frontend:**

```bash
cd client
heroku create school-erp-client
heroku config:set REACT_APP_API_URL=https://school-erp-api.herokuapp.com/api
git push heroku main
```

---

## 🔀 Option 4: Separate Frontend + Backend Deployment

**Best for:** Maximum flexibility, CDN for frontend, independent scaling

### Deploy Backend (API Server)

Follow Option 2 (VPS) for backend deployment on one server.

### Deploy Frontend to Vercel/Netlify

#### Vercel Deployment

1. **Create Vercel Account:** [vercel.com](https://vercel.com)

2. **Connect Repository:**
   ```bash
   npm i -g vercel
   cd client
   vercel
   ```

3. **Set Environment Variables in Vercel Dashboard:**
   ```
   REACT_APP_API_URL=https://your-backend-api.com/api
   REACT_APP_SCHOOL_NAME=Your School Name
   ```

4. **Vercel will automatically build and deploy**

#### Netlify Deployment

1. **Create Netlify Account:** [netlify.com](https://netlify.com)

2. **Deploy via CLI:**
   ```bash
   npm i -g netlify-cli
   cd client
   netlify init
   netlify deploy --prod
   ```

3. **Set Build Settings:**
   - Build command: `npm run build`
   - Publish directory: `build`
   - Environment variables: Same as Vercel

---

## ✅ Post-Deployment Checklist

### Critical Checks

- [ ] **Server is running:** `curl http://your-domain.com/api/health`
- [ ] **Frontend loads:** Open http://your-domain.com in browser
- [ ] **Login works:** admin@school.com / your-password
- [ ] **Database connected:** Check dashboard stats load
- [ ] **CORS working:** No CORS errors in browser console

### Module Testing

- [ ] **User Management:** Create/edit/delete users
- [ ] **Student Admission:** Admit a test student
- [ ] **Attendance:** Mark attendance for a class
- [ ] **Fee Collection:** Collect a test fee
- [ ] **Exam & Results:** Create exam and add results
- [ ] **Library:** Add a book and issue to student
- [ ] **Transport:** Create a bus route
- [ ] **Hostel:** Create room and allocate student
- [ ] **Payroll:** Generate a test payslip
- [ ] **Reports:** Export students/fees as PDF/Excel

### Security Checks

- [ ] **HTTPS enabled:** https://your-domain.com works
- [ ] **Strong passwords:** All credentials are strong
- [ ] **Environment variables:** No secrets in code
- [ ] **Database secured:** MySQL has authentication
- [ ] **API rate limiting:** Enabled (default in server)
- [ ] **File upload limits:** Configured (default 5MB)

### Performance Checks

- [ ] **Page load time:** < 3 seconds
- [ ] **API response time:** < 500ms
- [ ] **Database queries:** No slow queries
- [ ] **Caching enabled:** Static assets cached
- [ ] **Gzip compression:** Enabled in Nginx

---

## 🔒 SSL/HTTPS Setup

### Using Let's Encrypt (Free)

```bash
# Install Certbot
apt install -y certbot python3-certbot-nginx

# Obtain SSL certificate
certbot --nginx -d your-domain.com -d www.your-domain.com

# Auto-renewal setup (already configured by certbot)
certbot renew --dry-run
```

### Using Cloudflare (Free CDN + SSL)

1. **Create Cloudflare Account:** [cloudflare.com](https://cloudflare.com)

2. **Add Your Domain:**
   - Add your domain to Cloudflare
   - Update nameservers at your domain registrar

3. **Enable SSL/TLS:**
   - Go to SSL/TLS → Overview
   - Set to "Flexible" or "Full" mode

4. **Enable CDN:**
   - Caching → Configuration → Everything
   - This caches all static assets

---

## 💾 Backup Strategy

### Automated Database Backups

The server includes a backup script. Run it daily:

```bash
# Manual backup
cd /var/www/school-erp/server
node scripts/backup-db.js

# Automated daily backup via cron
crontab -e

# Add this line (runs daily at 2 AM)
0 2 * * * cd /var/www/school-erp/server && node scripts/backup-db.js >> logs/backup.log 2>&1
```

### Docker Backup

```bash
# Backup MySQL volume
docker run --rm -v school-erp_mysql_data:/data -v $(pwd):/backup alpine tar czf /backup/mysql-backup-$(date +%Y%m%d).tar.gz -C /data .

# Restore from backup
docker run --rm -v school-erp_mysql_data:/data -v $(pwd):/backup alpine tar xzf /backup/mysql-backup-YYYYMMDD.tar.gz -C /data
```

### File Uploads Backup

```bash
# Backup uploads directory
tar -czf uploads-backup-$(date +%Y%m%d).tar.gz /var/www/school-erp/server/uploads/

# Restore
tar -xzf uploads-backup-YYYYMMDD.tar.gz -C /var/www/school-erp/server/
```

### Off-Site Backup (Recommended)

Use tools like:
- **AWS S3:** `aws s3 sync /var/www/school-erp/backups s3://your-bucket/`
- **Google Drive:** Use `rclone` to sync to Google Drive
- **Dropbox:** Use `rclone` for Dropbox sync

---

## 📊 Monitoring & Logging

### PM2 Monitoring

```bash
# View real-time logs
pm2 logs school-erp-server

# View process status
pm2 status

# Monitor CPU/Memory
pm2 monit

# View detailed info
pm2 show school-erp-server
```

### Nginx Logs

```bash
# Access logs
tail -f /var/log/nginx/access.log

# Error logs
tail -f /var/log/nginx/error.log
```

### Application Logs

```bash
# Server logs
tail -f /var/www/school-erp/server/logs/*.log

# PM2 logs
pm2 logs
```

### Uptime Monitoring

Use external monitoring services:
- **UptimeRobot:** [uptimerobot.com](https://uptimerobot.com) (Free, checks every 5 min)
- **Pingdom:** [pingdom.com](https://pingdom.com) (Paid, checks every 1 min)
- **Better Stack:** [betterstack.com](https://betterstack.com) (Free tier available)

---

## 🔧 Troubleshooting

### Server Won't Start

```bash
# Check if MySQL is running
systemctl status mysql

# Check if port 5000 is in use
netstat -tulpn | grep 5000

# Check server logs
pm2 logs school-erp-server

# Test database connection
cd /var/www/school-erp/server
node -e "require('./config/prisma').$connect().then(() => { console.log('Connected'); process.exit(0) }).catch(err => { console.error(err); process.exit(1) })"
```

### Frontend Shows Blank Page

```bash
# Check if build exists
ls /var/www/school-erp/client/build/index.html

# Check Nginx configuration
nginx -t

# Check Nginx error logs
tail -f /var/log/nginx/error.log

# Check browser console for errors (F12)
```

### Database Connection Errors

```bash
# Test MySQL connection
mysql -u school_erp_user -p -h localhost school_erp

# Check DATABASE_URL in .env
cat /var/www/school-erp/server/.env | grep DATABASE_URL

# Run Prisma migrations again
cd /var/www/school-erp/server
npx prisma migrate deploy
```

### CORS Errors

Check that `FRONTEND_URL` in server `.env` matches your actual frontend URL:

```env
FRONTEND_URL=http://your-domain.com
```

### File Upload Failures

- Check Nginx `client_max_body_size` setting
- Check server `MAX_FILE_SIZE` in `.env`
- Check disk space: `df -h`

### Performance Issues

```bash
# Check server resources
free -h
df -h
top

# Check MySQL slow queries
mysql -u root -p
SHOW PROCESSLIST;

# Check PM2 memory usage
pm2 monit

# Restart PM2 processes
pm2 restart all
```

---

## 📞 Support

For deployment issues:

1. **Check logs:** PM2, Nginx, MySQL
2. **Verify environment variables:** All required vars set
3. **Test database connection:** MySQL is accessible
4. **Check firewall rules:** Ports 80, 443, 5000 open
5. **Review CORS configuration:** Frontend URL matches

---

## 🎯 Quick Commands Reference

```bash
# Start everything (Docker)
docker-compose up -d

# Start everything (VPS)
pm2 start ecosystem.config.js
systemctl start nginx
systemctl start mysql

# Stop everything (Docker)
docker-compose down

# Stop everything (VPS)
pm2 stop all
systemctl stop nginx

# View logs (Docker)
docker-compose logs -f server

# View logs (VPS)
pm2 logs
tail -f /var/log/nginx/error.log

# Backup database
docker-compose exec server node scripts/backup-db.js
# OR
cd /var/www/school-erp/server && node scripts/backup-db.js

# Update application
git pull && docker-compose up -d --build
# OR
git pull && cd client && npm run build && pm2 restart all

# Reset database (WARNING: deletes all data)
docker-compose down -v && docker-compose up -d
# OR
cd server && npx prisma migrate reset
```

---

**Deployment Guide Version:** 1.0.0  
**Last Updated:** April 7, 2026  
**Supported Platforms:** Docker, VPS, Railway, Render, Heroku, Vercel, Netlify

---

*End of Deployment Guide*
