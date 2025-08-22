# MemberPress Courses Copilot - Security Implementation

## Overview

The plugin now uses a secure authentication gateway to protect API keys from being exposed in the distributed code. This solves the critical security issue identified in the development plan.

## Architecture

```
WordPress Plugin → Auth Gateway (localhost:3001) → LiteLLM Proxy
     ↓                    ↓                           ↓
License Key      Validates & Adds Master Key    AI Services
```

## How It Works

1. **Plugin Side**: The WordPress plugin sends requests to the local auth gateway with a license key
2. **Auth Gateway**: Validates the license key and forwards the request with the real API key
3. **Security**: The master API key is never exposed in the plugin code

## Setup Instructions

### 1. Auth Gateway Location
The auth gateway is located at: `/Users/sethshoultes/Local Sites/memberpress-testing/auth-gateway/`

### 2. Starting the Gateway

```bash
cd "/Users/sethshoultes/Local Sites/memberpress-testing/auth-gateway"
./start-gateway.sh
```

Or manually:
```bash
npm start
```

### 3. Configuration

The gateway is configured via the `.env` file:
- `PORT=3001` - Port the gateway runs on
- `LITELLM_PROXY_URL` - The actual LiteLLM proxy URL
- `LITELLM_MASTER_KEY` - The secure master key (never in plugin code)
- `VALID_LICENSE_KEYS` - Comma-separated list of valid license keys

### 4. Testing

Check if the gateway is running:
```bash
curl http://localhost:3001/health
```

## Plugin Configuration

The plugin's `LLMService.php` has been updated to:
- Use `http://localhost:3001` as the endpoint
- Send `dev-license-key-001` as the authorization token
- All API keys have been removed from the plugin code

## Production Deployment

For production:
1. Deploy the auth gateway to a secure server (e.g., Heroku, AWS)
2. Update `AUTH_GATEWAY_URL` in `LLMService.php` to point to the production gateway
3. Implement proper license key validation (connect to MemberPress licensing API)
4. Use environment variables for all sensitive configuration

## Security Benefits

1. **No API Keys in Code**: Master keys are never exposed in distributed plugin files
2. **License Control**: Only valid MemberPress licenses can access the AI features
3. **Rate Limiting**: Can be implemented at the gateway level
4. **Usage Tracking**: Gateway can log usage per license
5. **Key Rotation**: API keys can be changed without updating plugin code

## Monitoring

The gateway logs all requests with timestamps:
```
[2025-08-20T20:55:00.123Z] - Proxying request for model: claude-3-sonnet-20240229
```

## Troubleshooting

If the plugin can't connect to the AI:
1. Check if the auth gateway is running: `curl http://localhost:3001/health`
2. Verify the license key in `LLMService.php` matches one in `.env`
3. Check the gateway logs for error messages
4. Ensure no firewall is blocking port 3001

## Next Steps

1. Implement proper license validation (connect to MemberPress API)
2. Add rate limiting per license key
3. Implement usage tracking and analytics
4. Deploy gateway to production infrastructure
5. Update plugin to use configurable gateway URL (via wp-config.php constant)