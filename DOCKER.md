# Kea API Admin - Docker Deployment Guide

## 🐳 Overview

This Docker setup allows you to run Kea API Admin with all dependencies in containers. Configuration files and application code are mounted as volumes, allowing easy updates without rebuilding containers.

## 📋 Prerequisites

- Docker Engine 20.10+
- Docker Compose 2.0+
- At least 2GB free RAM
- Ports 8080, 8081, 3307 available

## 🚀 Quick Start

### 1. Initial Setup

```bash
# Make the start script executable
chmod +x docker-start.sh

# Run the start script
./docker-start.sh
```

This will:
- Create a `.env` file from the template
- Create necessary directories
- Build and start all containers

### 2. Configure Environment

Edit `.env` file with your settings:

```bash
# Database credentials
DB_PASSWORD=your_secure_password
MYSQL_ROOT_PASSWORD=your_root_password

# RADIUS servers (if applicable)
RADIUS_PRIMARY_HOST=your-radius-server
RADIUS_PRIMARY_PASSWORD=your-radius-password
```

### 3. Start Services

```bash
# Start all services
docker-compose up -d

# Include phpMyAdmin for database management
docker-compose --profile tools up -d
```

### 4. Access the Application

- **Web Interface**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081
- **Database**: localhost:3307

## 📁 Volume Structure

### Application Code (Live Updates)
```
./                      → /var/www/html
```
Changes to code are immediately reflected without rebuild.

### Configuration Files (External)
```
./config/               → /var/www/html/config (read-only)
```
Update config files and restart: `docker-compose restart kea-api-admin`

### Persistent Data
```
kea-db-data            → MySQL database data
kea-backups            → Backup files
kea-logs               → Application logs
```

## 🔧 Configuration Management

### Update Configuration Without Rebuild

1. Edit configuration files in `./config/`:
   ```bash
   vim config/database.php
   vim config/kea.php
   vim config/radius.php
   ```

2. Restart the application:
   ```bash
   docker-compose restart kea-api-admin
   ```

### Update Application Code

Code changes are live due to volume mounting:
```bash
# Edit files normally
vim src/Controllers/SomeController.php

# No rebuild needed! Just refresh browser
```

### Update Dependencies

If you modify `composer.json`:
```bash
docker-compose exec kea-api-admin composer install
```

## 🛠️ Common Operations

### View Logs
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f kea-api-admin
docker-compose logs -f kea-db
```

### Execute Commands in Container
```bash
# PHP CLI
docker-compose exec kea-api-admin php -v

# Composer commands
docker-compose exec kea-api-admin composer update

# Database access
docker-compose exec kea-db mysql -u kea_user -p kea_db

# Shell access
docker-compose exec kea-api-admin bash
```

### Database Operations

**Create backup:**
```bash
docker-compose exec kea-db mysqldump -u kea_user -p kea_db > backup.sql
```

**Restore backup:**
```bash
docker-compose exec -T kea-db mysql -u kea_user -p kea_db < backup.sql
```

**Run migrations:**
```bash
docker-compose exec kea-db mysql -u kea_user -p kea_db < database/migrations/your_migration.sql
```

### Container Management

```bash
# Stop all services
docker-compose stop

# Start all services
docker-compose start

# Restart specific service
docker-compose restart kea-api-admin

# Remove containers (keeps data)
docker-compose down

# Remove containers and volumes (deletes data!)
docker-compose down -v
```

## 🔐 Security Considerations

### Production Deployment

1. **Change default passwords** in `.env`:
   ```bash
   DB_PASSWORD=strong_random_password
   MYSQL_ROOT_PASSWORD=another_strong_password
   ```

2. **Use secrets** instead of environment variables:
   ```yaml
   secrets:
     db_password:
       file: ./secrets/db_password.txt
   ```

3. **Enable HTTPS** with reverse proxy (Nginx/Traefik):
   ```bash
   # Add to docker-compose.yml
   labels:
     - "traefik.enable=true"
     - "traefik.http.routers.kea.rule=Host(`kea.yourdomain.com`)"
   ```

4. **Restrict phpMyAdmin** access or disable in production:
   ```bash
   # Remove --profile tools to disable phpMyAdmin
   docker-compose up -d
   ```

## 🐛 Troubleshooting

### Container won't start
```bash
# Check logs
docker-compose logs kea-api-admin

# Check container status
docker-compose ps

# Rebuild container
docker-compose build --no-cache kea-api-admin
```

### Database connection issues
```bash
# Check if database is ready
docker-compose exec kea-db mysqladmin ping -h localhost -u root -p

# Test connection from app container
docker-compose exec kea-api-admin ping kea-db
```

### Permission issues
```bash
# Fix ownership
docker-compose exec kea-api-admin chown -R www-data:www-data /var/www/html/backups
docker-compose exec kea-api-admin chown -R www-data:www-data /var/www/html/logs
```

### Port conflicts
```bash
# Change ports in .env or docker-compose.yml
WEB_PORT=8090
DB_PORT_EXTERNAL=3308
```

## 📊 Monitoring

### Container Health
```bash
# Check health status
docker-compose ps

# View resource usage
docker stats
```

### Application Logs
```bash
# Apache logs
docker-compose exec kea-api-admin tail -f /var/log/apache2/error.log

# Application logs
docker-compose exec kea-api-admin tail -f logs/*.log
```

## 🔄 Updates and Maintenance

### Update Application
```bash
# Pull latest code (if using git)
git pull

# Restart services
docker-compose restart kea-api-admin
```

### Update Docker Images
```bash
# Pull latest base images
docker-compose pull

# Rebuild containers
docker-compose build --pull

# Restart with new images
docker-compose up -d
```

### Cleanup
```bash
# Remove unused images
docker image prune -a

# Remove unused volumes
docker volume prune

# Full cleanup
docker system prune -a --volumes
```

## 🌐 Networking

### Custom Network
Containers communicate via `kea-network` bridge network.

### External Access
To connect from host or other containers:
- Application: `http://localhost:8080`
- Database: `localhost:3307`

### Link to External RADIUS Servers
Update `.env` with your RADIUS server details - no Docker rebuild needed!

## 📦 Backup Strategy

### Automated Backups
Create a cron job:
```bash
# Add to crontab
0 2 * * * cd /path/to/kea-api-admin && docker-compose exec -T kea-db mysqldump -u kea_user -p$DB_PASSWORD kea_db > backups/db-$(date +\%Y\%m\%d).sql
```

### Volume Backups
```bash
# Backup volumes
docker run --rm -v kea-db-data:/data -v $(pwd)/backups:/backup alpine tar czf /backup/db-data.tar.gz /data
```

## 🎯 Production Checklist

- [ ] Change all default passwords
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Configure HTTPS/SSL
- [ ] Set up automated backups
- [ ] Configure log rotation
- [ ] Restrict database access
- [ ] Set up monitoring/alerting
- [ ] Document your specific configuration
- [ ] Test disaster recovery procedure

## 📞 Support

For issues or questions:
1. Check logs: `docker-compose logs`
2. Review this documentation
3. Check Docker and Docker Compose versions
4. Verify port availability

---

**Happy Dockerizing! 🚀**
