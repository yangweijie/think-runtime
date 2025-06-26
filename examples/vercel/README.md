# ThinkPHP Runtime Vercel Example

This example demonstrates how to deploy a ThinkPHP application to Vercel using serverless functions.

## Quick Start

1. **Install dependencies:**
   ```bash
   composer install
   ```

2. **Configure environment variables:**
   Create a `.env` file or set environment variables in Vercel dashboard:
   ```env
   DATABASE_HOST=your-database-host
   DATABASE_NAME=your-database-name
   DATABASE_USER=your-database-user
   DATABASE_PASSWORD=your-database-password
   REDIS_HOST=your-redis-host
   REDIS_PASSWORD=your-redis-password
   ```

3. **Local development:**
   ```bash
   # Install Vercel CLI
   npm i -g vercel
   
   # Start local development server
   vercel dev
   ```

4. **Deploy to Vercel:**
   ```bash
   # Deploy to production
   vercel --prod
   ```

## Configuration

### vercel.json

The `vercel.json` file configures the serverless function:

```json
{
  "version": 2,
  "functions": {
    "api/index.php": {
      "runtime": "vercel-php@0.6.0",
      "maxDuration": 10
    }
  },
  "routes": [
    {
      "src": "/(.*)",
      "dest": "/api/index.php"
    }
  ]
}
```

### Runtime Configuration

Configure the Vercel runtime in `composer.json`:

```json
{
  "extra": {
    "runtime": {
      "class": "Think\\Runtime\\Runtime\\VercelRuntime",
      "enable_cors": true,
      "enable_static_cache": true,
      "max_execution_time": 10
    }
  }
}
```

## Features

- ✅ Serverless function deployment
- ✅ Automatic CORS handling
- ✅ Static file caching
- ✅ Environment-based configuration
- ✅ Error handling and logging
- ✅ Cold start optimization

## Environment Variables

Set these in your Vercel dashboard or `.env` file:

- `DATABASE_HOST` - Database hostname
- `DATABASE_NAME` - Database name
- `DATABASE_USER` - Database username
- `DATABASE_PASSWORD` - Database password
- `REDIS_HOST` - Redis hostname
- `REDIS_PASSWORD` - Redis password
- `APP_DEBUG` - Enable debug mode (true/false)

## API Routes

All routes are handled by the single `api/index.php` function:

- `GET /` - Home page
- `GET /api/users` - Get users
- `POST /api/users` - Create user
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user

## Limitations

- Maximum execution time: 10 seconds (Hobby plan)
- Memory limit: 1024MB (Hobby plan)
- No persistent file storage
- Cold starts may affect performance

## Best Practices

1. **Use external storage:** Store files in S3, Cloudinary, etc.
2. **Cache frequently accessed data:** Use Redis or external cache
3. **Optimize cold starts:** Minimize dependencies and initialization
4. **Handle timeouts gracefully:** Implement proper error handling
5. **Monitor performance:** Use Vercel Analytics and logging
