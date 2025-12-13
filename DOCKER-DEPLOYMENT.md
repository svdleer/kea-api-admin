# Docker Deployment Scenarios

## Scenario 1: Using External/Host Database (Recommended for existing infrastructure)

This is the default configuration - perfect when you already have MySQL running on your host machine or external server.

### Setup Steps:

1. **Configure `.env` for host database:**
```bash
cp .env.docker .env
vim .env
```

Set these values:
```bash
# Use host.docker.internal to connect to host MySQL
DB_HOST=host.docker.internal
DB_PORT=3306
DB_NAME=kea_db
DB_USER=kea_user
DB_PASSWORD=your_actual_password
```

2. **Start only the application container:**
```bash
docker-compose up -d
```

3. **Access the application:**
- Web: http://localhost:8080
- Database: Uses your existing MySQL server

### Benefits:
- ✅ Uses existing MySQL infrastructure
- ✅ No need to migrate data
- ✅ Simpler setup
- ✅ Better for production environments with existing databases

---

## Scenario 2: Using Docker Database (For development/testing)

Use this when you want everything containerized.

### Setup Steps:

1. **Configure `.env` for Docker database:**
```bash
cp .env.docker .env
vim .env
```

Set these values:
```bash
# Use Docker MySQL container
DB_HOST=kea-db
DB_PORT=3306
DB_NAME=kea_db
DB_USER=kea_user
DB_PASSWORD=kea_secure_password
MYSQL_ROOT_PASSWORD=root_secure_password
```

2. **Uncomment the depends_on section in `docker-compose.yml`:**
```yaml
services:
  kea-api-admin:
    # ... other config ...
    depends_on:
      kea-db:
        condition: service_healthy
```

3. **Start with database profile:**
```bash
docker-compose --profile with-db up -d
```

4. **Access the application:**
- Web: http://localhost:8080
- Database: localhost:3307
- phpMyAdmin: http://localhost:8081 (with `--profile tools`)

### Benefits:
- ✅ Completely isolated environment
- ✅ Easy to reset/rebuild
- ✅ Good for development
- ✅ No host MySQL required

---

## Quick Commands

### Using External Database (Default):
```bash
# Start application only
docker-compose up -d

# View logs
docker-compose logs -f kea-api-admin

# Stop
docker-compose stop

# Restart
docker-compose restart
```

### Using Docker Database:
```bash
# Start with Docker database
docker-compose --profile with-db up -d

# Start with database + phpMyAdmin
docker-compose --profile with-db --profile tools up -d

# View all logs
docker-compose --profile with-db logs -f

# Stop everything
docker-compose --profile with-db stop
```

---

## Connecting to Host Database from Docker

When `DB_HOST=host.docker.internal`, the Docker container can access services on the host machine.

**Important:** Make sure your MySQL server allows connections from Docker:
```sql
-- Grant access from Docker network
GRANT ALL PRIVILEGES ON kea_db.* TO 'kea_user'@'172.%' IDENTIFIED BY 'password';
FLUSH PRIVILEGES;
```

Or bind MySQL to all interfaces in `/etc/mysql/my.cnf`:
```ini
[mysqld]
bind-address = 0.0.0.0
```

---

## Troubleshooting

### Can't connect to host database:
```bash
# Test from inside container
docker-compose exec kea-api-admin ping host.docker.internal

# Check if MySQL is listening on host
netstat -tlnp | grep 3306

# Test connection
docker-compose exec kea-api-admin mysql -h host.docker.internal -u kea_user -p
```

### Database connection refused:
- Check MySQL bind-address allows external connections
- Verify user has permissions from Docker network
- Check firewall rules

### Container can't resolve host.docker.internal:
- Ensure you're using Docker 20.10+ 
- Try using host IP address instead
- On Linux, you may need to add `--add-host=host.docker.internal:host-gateway`

---

## Migration from Docker DB to Host DB

If you started with Docker database and want to switch:

1. **Export data from Docker database:**
```bash
docker-compose exec kea-db mysqldump -u kea_user -p kea_db > backup.sql
```

2. **Import to host database:**
```bash
mysql -h localhost -u kea_user -p kea_db < backup.sql
```

3. **Update `.env`:**
```bash
DB_HOST=host.docker.internal
```

4. **Restart application:**
```bash
docker-compose down
docker-compose up -d
```

---

**Choose the scenario that fits your infrastructure!** 🚀
