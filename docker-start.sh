#!/bin/bash
set -e

echo "🐳 Starting Kea API Admin Docker Environment..."

# Check if .env exists, if not copy from .env.docker
if [ ! -f .env ]; then
    echo "📝 Creating .env file from .env.docker template..."
    cp .env.docker .env
    echo "⚠️  Please review and update .env file with your configuration!"
    echo "   Then run this script again."
    exit 1
fi

# Create necessary directories if they don't exist
echo "📁 Creating necessary directories..."
mkdir -p backups logs config

# Pull latest images
echo "📦 Pulling Docker images..."
docker-compose pull

# Build the application container
echo "🔨 Building application container..."
docker-compose build

# Start the containers
echo "🚀 Starting containers..."
docker-compose up -d

# Wait for database to be ready
echo "⏳ Waiting for database to be ready..."
sleep 10

# Check container status
echo "✅ Container status:"
docker-compose ps

echo ""
echo "🎉 Kea API Admin is now running!"
echo ""
echo "📍 Access points:"
echo "   - Web Interface: http://localhost:8080"
echo "   - phpMyAdmin: http://localhost:8081 (run with: docker-compose --profile tools up -d)"
echo "   - Database: localhost:3307"
echo ""
echo "📚 Useful commands:"
echo "   - View logs: docker-compose logs -f"
echo "   - Stop: docker-compose stop"
echo "   - Restart: docker-compose restart"
echo "   - Remove: docker-compose down"
echo "   - Remove with data: docker-compose down -v"
echo ""
