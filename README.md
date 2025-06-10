# WordPress MCP Client

A WordPress plugin that acts as a Model Context Protocol (MCP) client using OpenAI to interact with the **WordPress MCP server** and provide AI-powered WordPress management capabilities.

## Overview

This plugin is specifically designed to work with the **WordPress MCP server** (`wordpress-mcp` plugin), creating a seamless bridge between OpenAI's AI models and your WordPress site's content and functionality. Through OpenAI's API with MCP integration, you can interact with your WordPress site using natural language - manage posts, users, analyze content, and perform administrative tasks through an AI assistant that has direct access to your WordPress data.

The plugin provides both a modern React-based admin interface and comprehensive WP-CLI commands for all operations.

## Features

- **WordPress MCP Server Integration**: Seamless connection to your WordPress MCP server with automatic JWT authentication
- **AI-Powered WordPress Management**: Use natural language to manage posts, pages, users, and site content through OpenAI
- **Modern React Admin Interface**: Beautiful chat interface built with WordPress Gutenberg components
- **Real-time AI Chat**: Interactive messaging with OpenAI models including conversation history
- **WordPress Data Access**: AI assistant has direct access to your WordPress content, users, and functionality
- **Comprehensive CLI Interface**: Complete WP-CLI command suite for all WordPress MCP operations
- **Model Selection**: Support for OpenAI's latest models (GPT-4.1, GPT-4.1-mini, GPT-4.1-nano)
- **JWT Authentication**: Automatic token management for secure WordPress MCP server communication
- **Configuration Management**: Secure storage of API keys and settings
- **Connection Testing**: Built-in tools to test OpenAI and WordPress MCP server connectivity
- **Token Usage Tracking**: Monitor OpenAI API token consumption
- **Markdown Support**: Rich formatting for AI responses in the admin interface
- **Security**: Automatic header masking and secure credential storage

## Requirements

- **WordPress MCP Plugin**: This plugin is built specifically for the `wordpress-mcp` plugin and **cannot function without it**
- WordPress 6.4 or higher
- PHP 8.0 or higher
- OpenAI API key (required for AI functionality)
- WP-CLI (for command-line interface)
- Node.js 18.12.0+ and npm 8.19.2+ (for development)

## Installation

### Prerequisites

**IMPORTANT**: This plugin requires the `wordpress-mcp` plugin to be installed and activated first. The WordPress MCP Client cannot function without the WordPress MCP server.

1. **Install and activate the WordPress MCP Plugin**:

   ```bash
   # Install the wordpress-mcp plugin first - this provides the MCP server
   wp plugin install wordpress-mcp --activate

   # OR manually install and activate through WordPress admin
   ```

2. **Verify WordPress MCP server is running**:

   ```bash
   # Check if the MCP endpoint is accessible
   curl -I http://your-site.com/wp-json/wp/v2/wpmcp/streamable
   # Should return 200 OK or 401 Unauthorized (both indicate the endpoint exists)
   ```

### Install WordPress MCP Client

3. **Clone or download this plugin** to your WordPress plugins directory:

   ```bash
   cd wp-content/plugins/
   git clone <repository-url> wordpress-mcp-client
   # OR extract the plugin files to wordpress-mcp-client/
   ```

4. **Install Node.js dependencies** and build the React components:

   ```bash
   cd wordpress-mcp-client
   npm install
   npm run build
   ```

5. **Activate the plugin** in WordPress admin or via WP-CLI:
   ```bash
   wp plugin activate wordpress-mcp-client
   ```

## Configuration

### Set up OpenAI API Key

Configure your OpenAI API key using WP-CLI:

```bash
wp mcp config set openai_api_key sk-your-openai-api-key-here
```

### Configure Model (Optional)

Set the default OpenAI model to use:

```bash
wp mcp config set model gpt-4.1
```

### Available Models

The plugin supports the following OpenAI models:

- `gpt-4.1` (default)
- `gpt-4.1-mini`
- `gpt-4.1-nano`

## Usage

### Admin Interface

After activating the plugin, you'll find a new "MCP Client" menu in your WordPress admin:

#### Chat Interface (MCP Client → Chat)

1. **Navigate to MCP Client → Chat** in your WordPress admin dashboard
2. The chat interface provides:

   - **Real-time messaging** with OpenAI
   - **Model selection** dropdown (GPT-4.1, GPT-4.1-mini, GPT-4.1-nano)
   - **Conversation history** with timestamps
   - **Markdown rendering** for AI responses
   - **Token usage tracking** for each response
   - **Connection testing** to verify OpenAI connectivity
   - **Chat clearing** functionality

3. **WordPress MCP Integration**: The plugin automatically connects to your WordPress MCP server at:
   - **Endpoint**: `/wp-json/wp/v2/wpmcp/streamable`
   - **Authentication**: JWT Bearer Token (automatically managed)
   - **Capabilities**: Access to WordPress content, posts, pages, users, and functionality

#### Settings Interface (MCP Client → Settings)

1. **Navigate to MCP Client → Settings** to view:
   - Configuration status (API key, MCP server status, JWT token status)
   - Available models and current selection
   - WordPress MCP server connection details
   - Available CLI commands reference

### CLI Commands

The plugin provides comprehensive WP-CLI commands under the `mcp` namespace:

#### Configuration Management

```bash
# Set configuration values
wp mcp config set <key> <value>

# Get configuration values (sensitive values are masked)
wp mcp config get <key>

# List all configuration
wp mcp config list

# Reset all configuration to defaults
wp mcp config reset
```

#### Chat with AI

```bash
# Send a message to OpenAI with WordPress MCP context
wp mcp chat "Hello, can you help me with WordPress?"

# Use a specific model
wp mcp chat "Analyze my site" --model=gpt-4.1-mini

# Chat with conversation history maintained automatically
wp mcp chat "What are my latest posts?"
```

#### Testing and Diagnostics

```bash
# Test OpenAI connection
wp mcp test

# List available MCP tools (requires WordPress MCP server)
wp mcp tools
```

#### Legacy Server Management

```bash
# Add external MCP servers (advanced usage)
wp mcp servers add <name> <url>

# Remove MCP servers
wp mcp servers remove <name>

# List configured servers
wp mcp servers list
```

**Note**: The plugin primarily uses the internal WordPress MCP server. External server management is available for advanced configurations.

## Examples

### Basic Setup and Usage

```bash
# 1. Configure OpenAI API key
wp mcp config set openai_api_key sk-your-api-key-here

# 2. Test the connection (tests both OpenAI and WordPress MCP server)
wp mcp test

# 3. Start asking WordPress-specific questions
wp mcp chat "What are my latest 5 blog posts?"
wp mcp chat "How many users are registered on my site?"
wp mcp chat "Show me my most popular posts this month"
wp mcp chat "Create a draft post about AI in WordPress"
wp mcp chat "What themes and plugins are currently active?"
```

### WordPress Management Examples

With the WordPress MCP server integration, you can perform complex WordPress tasks using natural language:

```bash
# Content management
wp mcp chat "Find all posts with the tag 'AI' and show their titles"
wp mcp chat "Update the post titled 'Hello World' to published status"
wp mcp chat "Show me all pages that haven't been updated in 6 months"

# User management
wp mcp chat "List all administrator users"
wp mcp chat "How many new users registered this week?"

# Site analysis
wp mcp chat "What's the overall health of my WordPress site?"
wp mcp chat "Show me statistics about my content and users"
```

### Advanced Usage

```bash
# Use different models for different tasks
wp mcp chat "Write a brief summary" --model=gpt-4.1-nano
wp mcp chat "Analyze my site's content strategy in detail" --model=gpt-4.1

# Check available WordPress tools
wp mcp tools

# Monitor configuration status
wp mcp config list
```

## Configuration Options

| Setting          | Description                              | Default   | CLI Command                        |
| ---------------- | ---------------------------------------- | --------- | ---------------------------------- |
| `openai_api_key` | Your OpenAI API key                      | (empty)   | `wp mcp config set openai_api_key` |
| `model`          | Default OpenAI model to use              | `gpt-4.1` | `wp mcp config set model`          |
| `mcp_servers`    | Array of external MCP servers (advanced) | `[]`      | `wp mcp servers add`               |

## WordPress MCP Integration

The plugin automatically integrates with the WordPress MCP server:

### Internal MCP Server Configuration

```json
{
  "name": "wordpress-internal",
  "url": "/wp-json/wp/v2/wpmcp/streamable",
  "transport": "streamable",
  "authentication": "JWT Bearer Token (auto-managed)"
}
```

### JWT Authentication

- **Automatic Token Management**: Tokens are generated and refreshed automatically
- **Token Duration**: 1 hour with 5-minute refresh buffer
- **Secure Storage**: Tokens stored in WordPress options table
- **Error Handling**: Automatic retry and fallback mechanisms

### Available WordPress Tools

The internal MCP server provides tools for:

- **Content Management**: Posts, pages, comments
- **User Management**: User data and roles
- **Site Analytics**: Basic site statistics
- **WooCommerce**: E-commerce data (if WooCommerce is active)
- **Custom Post Types**: Support for custom content types

## Security

- **API Key Protection**: OpenAI API keys are stored securely and masked in output
- **JWT Authentication**: Secure token-based authentication with the WordPress MCP server
- **Input Validation**: All user inputs are sanitized and validated
- **Nonce Verification**: WordPress nonces protect against CSRF attacks
- **Capability Checks**: Admin-only access to sensitive functions
- **Header Masking**: Sensitive authentication headers are masked in CLI output

## Development

### File Structure

```
wordpress-mcp-client/
├── wordpress-mcp-client.php    # Main plugin file
├── package.json                 # Node.js dependencies and build scripts
├── .gitignore                   # Git ignore patterns
├── includes/
│   ├── Admin/
│   │   └── AdminInterface.php  # Admin interface controller
│   ├── Core/
│   │   ├── Client.php          # Main client singleton
│   │   ├── OpenAiMcpClient.php # OpenAI MCP integration
│   │   ├── CustomOpenAiClient.php # Custom OpenAI API client
│   │   ├── JwtTokenManager.php # JWT authentication manager
│   │   └── Settings.php        # Settings management
│   └── CLI/
│       └── McpCommands.php     # WP-CLI commands
├── src/                        # React source files
│   ├── index.js                # Main entry point
│   ├── components/
│   │   ├── ChatApp.js          # Chat interface component
│   │   └── SettingsApp.js      # Settings interface component
│   ├── utils/
│   └── styles/
├── build/                      # Built React assets (auto-generated)
│   ├── index.js                # Compiled JavaScript
│   ├── index.css               # Compiled styles
│   └── index.asset.php         # WordPress asset dependencies
└── README.md                   # This file
```

### Build Process

The plugin uses WordPress Scripts for building React components:

```bash
# Development build with watch mode
npm run start

# Production build
npm run build

# Linting
npm run lint:js
npm run lint:css

# Plugin zip creation
npm run plugin-zip
```

### Available Scripts

| Script               | Description                          |
| -------------------- | ------------------------------------ |
| `npm run build`      | Production build of React components |
| `npm run start`      | Development build with watch mode    |
| `npm run lint:js`    | JavaScript linting                   |
| `npm run lint:css`   | CSS/SCSS linting                     |
| `npm run format`     | Code formatting                      |
| `npm run plugin-zip` | Create plugin distribution zip       |

### Extending the Plugin

The plugin is designed to be extensible:

1. **Add new CLI commands** by extending the `McpCommands` class
2. **Create custom OpenAI integrations** by extending `CustomOpenAiClient`
3. **Add new React components** in the `src/components/` directory
4. **Implement custom authentication** by extending `JwtTokenManager`

## Troubleshooting

### Common Issues

1. **"WordPress MCP plugin is not active" error**

   - Install and activate the `wordpress-mcp` plugin (required dependency)
   - Verify the plugin is properly configured

2. **"OpenAI API key not configured" error**

   ```bash
   wp mcp config set openai_api_key sk-your-api-key-here
   ```

3. **Invalid API key format**

   - Ensure your OpenAI API key starts with `sk-` and is properly formatted
   - Support for both legacy (`sk-[48 chars]`) and project-based (`sk-proj-[key]`) formats
   - Check for extra spaces or characters

4. **JWT authentication failures**

   ```bash
   # Check JWT token status
   wp mcp config list

   # The plugin automatically manages JWT tokens, but you can test connectivity:
   wp mcp test
   ```

5. **Build/React component issues**

   ```bash
   # Reinstall dependencies and rebuild
   npm install
   npm run build
   ```

6. **WP-CLI commands not available**
   - Ensure WP-CLI is installed and working
   - Verify the plugin is activated
   - Check PHP version compatibility (requires PHP 8.0+)

### Debug Information

View current configuration and status:

```bash
# Check all configuration
wp mcp config list

# Test OpenAI connection
wp mcp test

# Check available MCP tools
wp mcp tools
```

### Error Logs

The plugin logs important events and errors:

- **WordPress Debug Log**: Standard WordPress error logging
- **MCP Client Logs**: Prefixed with "WordPress MCP Client:" or "MCP Client:"
- **JWT Token Events**: Detailed JWT token generation and refresh logs

## Dependencies

### PHP Dependencies

- WordPress core functions and APIs
- Custom OpenAI client implementation (no external SDK dependencies)

### JavaScript Dependencies

- `@wordpress/scripts`: Build tooling
- `@wordpress/components`: UI components
- `@wordpress/element`: React integration
- `@wordpress/api-fetch`: API communication
- `@wordpress/i18n`: Internationalization
- `marked`: Markdown parsing
- `dompurify`: HTML sanitization

## Support

For support and bug reports, please refer to the plugin's repository or contact the development team.

## License

This plugin is licensed under the GPL-2.0-or-later license.

## Changelog

### 1.0.0

- Initial release
- React-based admin interface with chat functionality
- OpenAI integration with custom API client
- WordPress MCP plugin integration with JWT authentication
- WP-CLI command interface with comprehensive commands
- WordPress Gutenberg components integration
- Real-time messaging with conversation history
- Model selection and token usage tracking
- Automatic JWT token management
- Security features including input validation and header masking
- Configuration management through WordPress options
- Connection testing and diagnostic tools
- Markdown rendering for AI responses
- Responsive admin interface design
