# Auth Gateway Configuration

## Overview
The MemberPress Courses Copilot plugin uses an authentication gateway to securely access AI services without exposing API keys in the plugin code. By default, the plugin uses the production auth gateway URL, but this can be overridden for development or testing purposes.

## Configuration

### Using the Default Gateway
No configuration is required. The plugin will automatically use the production auth gateway at:
```
https://memberpress-auth-gateway-49bbf7ff52ea.herokuapp.com
```

### Customizing the Gateway URL
To use a different auth gateway URL (for development or testing), add the following constant to your `wp-config.php` file:

```php
// In wp-config.php - add this above the "That's all, stop editing!" line
define('MPCC_AUTH_GATEWAY_URL', 'https://your-custom-gateway-url.com');
```

### Example Configuration
```php
// Development environment
define('MPCC_AUTH_GATEWAY_URL', 'https://dev-auth-gateway.example.com');

// Staging environment
define('MPCC_AUTH_GATEWAY_URL', 'https://staging-auth-gateway.example.com');

// Local testing (if running auth gateway locally)
define('MPCC_AUTH_GATEWAY_URL', 'http://localhost:8000');
```

## Security Notes
- The auth gateway URL should always use HTTPS in production
- Never expose API keys directly in the plugin code
- The auth gateway handles all API key management securely

## License Key Configuration
The plugin currently uses a development placeholder license key. In production, this will be integrated with the MemberPress licensing system. See `/docs/todo/LICENSING_IMPLEMENTATION.md` for implementation details.

## Troubleshooting

### Common Issues
1. **Connection errors**: Ensure the auth gateway URL is accessible from your WordPress server
2. **SSL certificate errors**: For local development, you may need to disable SSL verification (not recommended for production)
3. **Authentication errors**: Check that the license key is valid

### Debug Logging
To enable debug logging for API requests, add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('MPCC_LOG_LEVEL', 'debug'); // Optional: Set to 'info', 'warning', or 'error'
```

Then check the debug log at `/wp-content/plugins/memberpress-courses-copilot/logs/debug.log` for detailed API request/response information.