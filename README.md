# WordPress MCP Client

A WordPress plugin that acts as a Model Context Protocol (MCP) client using OpenAI to interact with MCP servers and AI assistants.

## Overview

This plugin enables WordPress to communicate with MCP (Model Context Protocol) servers through OpenAI's API, allowing you to integrate AI-powered tools and resources directly into your WordPress site via WP-CLI commands.

## Features

- **Admin Interface**: Beautiful React-based chat interface with real-time messaging
- **WordPress Integration**: Uses Gutenberg components for native WordPress experience
- **OpenAI Integration**: Uses the [openai-php/client](https://github.com/openai-php/client) SDK for seamless OpenAI API communication
- **MCP Server Management**: Configure and manage multiple MCP servers
- **CLI Interface**: Complete WP-CLI command suite for all operations
- **Configuration Management**: Secure storage of API keys and settings
- **Tool Discovery**: List and explore available MCP tools from connected servers
- **Message Routing**: Send messages to AI with MCP server context
- **Security**: Automatic header masking in CLI output and admin interface

## Requirements

- WordPress 6.4 or higher
- PHP 8.0 or higher
- WP-CLI (for command-line interface)
- OpenAI API key
- Composer (for dependency management)

## Installation

1. **Clone or download the plugin** to your WordPress plugins directory:

   ```bash
   cd wp-content/plugins/
   git clone <repository-url> wordpress-mcp-client
   # OR extract the plugin files to wordpress-mcp-client/
   ```

2. **Install dependencies** using Composer:

   ```bash
   cd wordpress-mcp-client
   composer install
   ```

3. **Activate the plugin** in WordPress admin or via WP-CLI:
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
wp mcp config set model gpt-4
```

### Add MCP Servers

Add one or more MCP servers to connect to:

```bash
# Basic server without authentication
wp mcp servers add wordpress-server http://localhost:3000

# Server with API key authentication
wp mcp servers add protected-server https://api.example.com/mcp --auth-header="X-API-Key: sk-12345"

# Server with Bearer token authentication
wp mcp servers add oauth-server https://secure.example.com/mcp --auth-header="Authorization: Bearer your-token"

# Server with limited tools and approval requirements
wp mcp servers add limited-server http://api.example.com/mcp --allowed-tools="search,list" --require-approval=always
```

## Usage

The plugin provides both a web-based admin interface and comprehensive CLI commands for all operations.

### Admin Interface

After activating the plugin, you'll find a new "MCP Client" menu in your WordPress admin:

1. **Navigate to MCP Client → Chat** in your WordPress admin dashboard
2. The chat interface provides:

   - Real-time messaging with OpenAI
   - Model selection (GPT-4.1, GPT-4.1-mini, GPT-4.1-nano)
   - MCP server selection for context
   - Connection testing
   - Chat history with timestamps
   - Token usage tracking

3. **Navigate to MCP Client → Settings** to view:
   - Configuration status (API key, servers, model)
   - Configured MCP servers with authentication details
   - Available CLI commands reference

The admin interface is fully responsive and uses WordPress Gutenberg components for a native experience.

### CLI Commands

The plugin provides several WP-CLI commands under the `mcp` namespace:

#### Configuration Management

```bash
# Set configuration values
wp mcp config set <key> <value>

# Get configuration values
wp mcp config get <key>

# List all configuration
wp mcp config list

# Reset all configuration to defaults
wp mcp config reset
```

#### MCP Server Management

```bash
# Add a new MCP server
wp mcp servers add <name> <url>

# Remove an MCP server
wp mcp servers remove <name>

# List all configured servers
wp mcp servers list
```

#### Chat with AI

```bash
# Send a message to OpenAI with MCP context
wp mcp chat "Hello, can you help me with WordPress?"

# Use a specific model
wp mcp chat "Analyze my site" --model=gpt-3.5-turbo

# Use specific MCP servers
wp mcp chat "Get my latest posts" --servers=wordpress-server
```

#### Testing and Tools

```bash
# Test OpenAI connection
wp mcp test

# List available MCP tools
wp mcp tools

# List tools from specific servers
wp mcp tools --servers=wordpress-server
```

## Examples

### Basic Setup and Usage

```bash
# 1. Configure OpenAI API key
wp mcp config set openai_api_key sk-your-api-key-here

# 2. Add a local WordPress MCP server
wp mcp servers add local-wp http://localhost:3000

# 3. Test the connection
wp mcp test

# 4. List available tools
wp mcp tools

# 5. Send a message
wp mcp chat "What are my latest 5 blog posts?"
```

### Advanced Usage

```bash
# Add multiple servers with different authentication
wp mcp servers add wordpress-server http://localhost:3000
wp mcp servers add analytics-server http://localhost:3001 --auth-header="X-API-Key: analytics-key-123"
wp mcp servers add crm-server https://api.crm.com/mcp --auth-header="Authorization: Bearer crm-token-456"

# Add server with tool restrictions for security
wp mcp servers add public-server https://public-api.com/mcp --allowed-tools="search,read" --require-approval=always

# Chat with specific authenticated servers
wp mcp chat "Get analytics data for my site" --servers=analytics-server

# Use multiple servers in one request
wp mcp chat "Get user data from CRM and analytics" --servers=crm-server,analytics-server

# Use a different model with authentication
wp mcp chat "Write a blog post about AI" --model=gpt-3.5-turbo --servers=wordpress-server
```

## Configuration Options

| Setting          | Description                     | Default |
| ---------------- | ------------------------------- | ------- |
| `openai_api_key` | Your OpenAI API key             | (empty) |
| `model`          | Default OpenAI model to use     | `gpt-4` |
| `mcp_servers`    | Array of configured MCP servers | `[]`    |

## MCP Server Configuration

MCP servers are configured with the following structure:

```json
{
  "name": "server-name",
  "url": "http://localhost:3000",
  "headers": {
    "Authorization": "Bearer your-token",
    "X-API-Key": "your-api-key"
  },
  "allowed_tools": ["search", "list", "create"],
  "require_approval": "auto"
}
```

### Authentication Headers

The `headers` field supports common authentication patterns:

- **API Keys**: `"X-API-Key": "your-api-key"`
- **Bearer Tokens**: `"Authorization": "Bearer your-token"`
- **Basic Auth**: `"Authorization": "Basic base64-encoded-credentials"`
- **Custom Headers**: Any custom authentication headers your MCP server requires

### Tool Restrictions

- **allowed_tools**: Array of tool names the client can access
- **require_approval**: Controls when approval is needed:
  - `"auto"`: Automatic approval for safe operations
  - `"always"`: Always require approval before tool execution
  - `"never"`: Never require approval (use with caution)

## Security

- API keys are stored securely in WordPress options
- Sensitive values are masked in CLI output
- Input validation for all user-provided data
- URL validation for MCP server endpoints

## Development

### File Structure

```
wordpress-mcp-client/
├── wordpress-mcp-client.php    # Main plugin file
├── composer.json                # PHP dependencies and autoloading
├── package.json                 # Node.js dependencies for React build
├── webpack.config.js            # Build configuration
├── includes/
│   ├── Admin/
│   │   └── AdminInterface.php  # Admin interface controller
│   ├── Core/
│   │   ├── Client.php          # Main client singleton
│   │   ├── OpenAiMcpClient.php # OpenAI MCP integration
│   │   └── Settings.php        # Settings management
│   └── CLI/
│       └── McpCommands.php     # WP-CLI commands
├── src/                        # React source files
│   ├── admin.js                # Main admin entry point
│   └── admin/
│       ├── admin.scss          # Admin styles
│       └── components/
│           ├── ChatApp.js      # Chat interface component
│           └── SettingsApp.js  # Settings interface component
├── build/                      # Built React assets (auto-generated)
│   ├── admin.js                # Compiled JavaScript
│   ├── admin.css               # Compiled styles
│   └── admin.asset.php         # WordPress asset dependencies
└── README.md                   # This file
```

### Extending the Plugin

The plugin is designed to be extensible. You can:

1. Add new CLI commands by extending the `McpCommands` class
2. Create custom MCP server integrations
3. Add new OpenAI model configurations
4. Implement custom authentication methods

## Troubleshooting

### Common Issues

1. **Composer dependencies not installed**

   ```bash
   cd wp-content/plugins/wordpress-mcp-client
   composer install
   ```

2. **Invalid API key format**

   - Ensure your OpenAI API key starts with `sk-` and is 51 characters long
   - Check for extra spaces or characters

3. **MCP server connection issues**

   - Verify the server URL is accessible
   - Check server authentication if required
   - Ensure the server implements MCP protocol correctly

4. **WP-CLI commands not available**
   - Ensure WP-CLI is installed and working
   - Verify the plugin is activated
   - Check PHP version compatibility

### Debug Information

View current configuration:

```bash
wp mcp config list
```

Test OpenAI connection:

```bash
wp mcp test
```

## Support

For support and bug reports, please refer to the plugin's repository or contact the development team.

## License

This plugin is licensed under the GPL-2.0-or-later license.

## Changelog

### 1.0.0

- Initial release
- React-based admin interface with chat functionality
- OpenAI integration with MCP support
- WP-CLI command interface
- WordPress Gutenberg components integration
- Real-time messaging with token usage tracking
- Model and server selection in admin interface
- Basic configuration management
- MCP server management with authentication support
- Tool discovery and chat functionality
- Security headers masking in both CLI and admin interface
