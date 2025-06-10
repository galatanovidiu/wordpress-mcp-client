import { useState, useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import {
  Button,
  Card,
  CardHeader,
  CardBody,
  Flex,
  FlexBlock,
  FlexItem,
  TextControl,
  SelectControl,
  Notice,
  Spinner,
  Icon,
} from "@wordpress/components";
import { settings, cog } from "@wordpress/icons";

const SettingsApp = () => {
  const [config, setConfig] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [notice, setNotice] = useState(null);
  const [apiKey, setApiKey] = useState("");
  const [selectedModel, setSelectedModel] = useState("gpt-4.1");

  useEffect(() => {
    loadConfig();
  }, []);

  const loadConfig = async () => {
    setIsLoading(true);
    try {
      const formData = new FormData();
      formData.append("action", "mcp_get_config");
      formData.append("nonce", window.mcpClientAdmin.nonce);

      const response = await apiFetch({
        url: window.mcpClientAdmin.apiUrl,
        method: "POST",
        body: formData,
      });

      if (!response.success) {
        throw new Error(response.data || "Failed to load configuration");
      }

      setConfig(response.data);
      setSelectedModel(response.data.model || "gpt-4.1");
    } catch (err) {
      console.error("Failed to load data:", err);
      setNotice({
        type: "error",
        message:
          err.message ||
          "Failed to load settings. Please try refreshing the page.",
      });
    } finally {
      setIsLoading(false);
    }
  };

  const saveConfig = async () => {
    if (!apiKey && !selectedModel) {
      setNotice({
        type: "error",
        message: "Please provide API key or select a model",
      });
      return;
    }

    setIsSaving(true);
    try {
      const formData = new FormData();
      formData.append("action", "mcp_save_config");
      formData.append("nonce", window.mcpClientAdmin.nonce);

      if (apiKey) {
        formData.append("api_key", apiKey);
      }
      if (selectedModel) {
        formData.append("model", selectedModel);
      }

      const response = await apiFetch({
        url: window.mcpClientAdmin.apiUrl,
        method: "POST",
        body: formData,
      });

      if (response.success) {
        setNotice({
          type: "success",
          message: response.data,
        });
        setApiKey("");
        loadConfig();
      } else {
        setNotice({
          type: "error",
          message: response.data,
        });
      }
    } catch (err) {
      setNotice({
        type: "error",
        message: "Failed to save configuration",
      });
    } finally {
      setIsSaving(false);
    }
  };

  const testJWTToken = async () => {
    setIsSaving(true);
    try {
      const formData = new FormData();
      formData.append("action", "mcp_refresh_jwt_token");
      formData.append("nonce", window.mcpClientAdmin.nonce);

      const response = await apiFetch({
        url: window.mcpClientAdmin.apiUrl,
        method: "POST",
        body: formData,
      });

      if (response.success) {
        setNotice({
          type: "success",
          message:
            response.data.message + " Check browser console for details.",
        });
        loadConfig();
      } else {
        setNotice({
          type: "error",
          message: response.data + " Check error logs for details.",
        });
      }
    } catch (err) {
      setNotice({
        type: "error",
        message: "Failed to test JWT token generation: " + err.message,
      });
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return (
      <div style={{ textAlign: "center", padding: "40px" }}>
        <Spinner />
        <p>{__("Loading settings...", "wordpress-mcp-client")}</p>
      </div>
    );
  }

  return (
    <div className="mcp-settings-app">
      <Flex direction="column" gap={4}>
        {notice && (
          <Notice
            status={notice.type}
            onRemove={() => setNotice(null)}
            isDismissible={true}
          >
            {notice.message}
          </Notice>
        )}

        {/* Configuration Card */}
        <Card>
          <CardHeader>
            <Flex>
              <FlexItem>
                <Icon icon={settings} size={24} />
              </FlexItem>
              <FlexBlock>
                <h2>{__("Configuration", "wordpress-mcp-client")}</h2>
              </FlexBlock>
            </Flex>
          </CardHeader>

          <CardBody>
            <div className="config-section">
              <div>
                <h4>{__("OpenAI API Key", "wordpress-mcp-client")}</h4>
                <TextControl
                  value={apiKey}
                  onChange={setApiKey}
                  placeholder="sk-your-api-key-here"
                  help={__(
                    "Enter your OpenAI API key (starts with sk-)",
                    "wordpress-mcp-client"
                  )}
                />
                <p
                  style={{
                    color: config?.api_key_configured ? "#46b450" : "#dc3232",
                    fontWeight: "bold",
                  }}
                >
                  {config?.api_key_configured
                    ? "✅ Currently Configured"
                    : "❌ Not Configured"}
                </p>
              </div>

              <div>
                <h4>{__("Default Model", "wordpress-mcp-client")}</h4>
                <SelectControl
                  value={selectedModel}
                  options={[
                    { label: "GPT-4.1", value: "gpt-4.1" },
                    { label: "GPT-4.1-mini", value: "gpt-4.1-mini" },
                    { label: "GPT-4.1-nano", value: "gpt-4.1-nano" },
                  ]}
                  onChange={setSelectedModel}
                  help={__(
                    "Choose the default OpenAI model to use",
                    "wordpress-mcp-client"
                  )}
                />
              </div>
            </div>

            <Flex justify="flex-end" style={{ marginTop: "20px" }}>
              <Button
                variant="primary"
                onClick={saveConfig}
                disabled={isSaving}
                isBusy={isSaving}
              >
                {isSaving
                  ? __("Saving...", "wordpress-mcp-client")
                  : __("Save Configuration", "wordpress-mcp-client")}
              </Button>
            </Flex>
          </CardBody>
        </Card>

        {/* WordPress MCP Server Card */}
        <Card>
          <CardHeader>
            <Flex>
              <FlexItem>
                <Icon icon={cog} size={24} />
              </FlexItem>
              <FlexBlock>
                <h2>{__("WordPress MCP Server", "wordpress-mcp-client")}</h2>
              </FlexBlock>
            </Flex>
          </CardHeader>

          <CardBody>
            <div style={{ display: "grid", gap: "16px" }}>
              <div className="server-card">
                <h4 style={{ margin: "0 0 8px 0", color: "#2271b1" }}>
                  {__("Internal WordPress Server", "wordpress-mcp-client")}
                </h4>

                <p style={{ margin: "0 0 8px 0", color: "#666" }}>
                  <strong>{__("URL:", "wordpress-mcp-client")}</strong>{" "}
                  {window.location.origin}/wp-json/wp/v2/wpmcp/streamable
                </p>

                <p style={{ margin: "0 0 8px 0", color: "#666" }}>
                  <strong>{__("Status:", "wordpress-mcp-client")}</strong>{" "}
                  {config?.mcp_server_enabled ? (
                    <span style={{ color: "#46b450" }}>
                      ✅{" "}
                      {__(
                        "WordPress MCP Plugin Active",
                        "wordpress-mcp-client"
                      )}
                    </span>
                  ) : (
                    <span style={{ color: "#dc3232" }}>
                      ❌{" "}
                      {__(
                        "WordPress MCP Plugin Required",
                        "wordpress-mcp-client"
                      )}
                    </span>
                  )}
                </p>

                <p style={{ margin: "0 0 8px 0", color: "#666" }}>
                  <strong>
                    {__("Authentication:", "wordpress-mcp-client")}
                  </strong>{" "}
                  {config?.jwt_token_status?.has_token ? (
                    <span style={{ color: "#46b450" }}>
                      ✅ {__("JWT Token Active", "wordpress-mcp-client")}
                      {config.jwt_token_status.expires_in > 0 && (
                        <span style={{ fontSize: "12px", marginLeft: "8px" }}>
                          ({__("expires in", "wordpress-mcp-client")}{" "}
                          {Math.floor(config.jwt_token_status.expires_in / 60)}
                          m)
                        </span>
                      )}
                    </span>
                  ) : (
                    <span style={{ color: "#dc3232" }}>
                      ❌ {__("JWT Token Required", "wordpress-mcp-client")}
                    </span>
                  )}
                </p>

                <div style={{ marginTop: "12px" }}>
                  <Button
                    variant="secondary"
                    onClick={testJWTToken}
                    disabled={isSaving}
                    isBusy={isSaving}
                    size="small"
                  >
                    {__("Test JWT Token Generation", "wordpress-mcp-client")}
                  </Button>
                </div>

                <p style={{ margin: "0", fontSize: "14px", color: "#666" }}>
                  {__(
                    "This server provides access to WordPress content, posts, pages, users, and other WordPress functionality through the Model Context Protocol.",
                    "wordpress-mcp-client"
                  )}
                </p>

                {!config?.mcp_server_enabled && (
                  <div
                    style={{
                      marginTop: "12px",
                      padding: "12px",
                      backgroundColor: "#fff3cd",
                      border: "1px solid #ffeaa7",
                      borderRadius: "4px",
                    }}
                  >
                    <p style={{ margin: "0", color: "#856404" }}>
                      <strong>{__("Note:", "wordpress-mcp-client")}</strong>{" "}
                      {__(
                        "The WordPress MCP plugin is required for this client to work. Please ensure it's installed and activated.",
                        "wordpress-mcp-client"
                      )}
                    </p>
                  </div>
                )}

                {config?.jwt_token_status?.is_expired && (
                  <div
                    style={{
                      marginTop: "12px",
                      padding: "12px",
                      backgroundColor: "#f8d7da",
                      border: "1px solid #f5c6cb",
                      borderRadius: "4px",
                    }}
                  >
                    <p style={{ margin: "0", color: "#721c24" }}>
                      <strong>{__("Warning:", "wordpress-mcp-client")}</strong>{" "}
                      {__(
                        "JWT authentication token has expired. It will be automatically refreshed on the next request.",
                        "wordpress-mcp-client"
                      )}
                    </p>
                  </div>
                )}
              </div>
            </div>
          </CardBody>
        </Card>

        {/* CLI Commands Reference Card */}
        <Card>
          <CardHeader>
            <h2>{__("CLI Commands Reference", "wordpress-mcp-client")}</h2>
          </CardHeader>

          <CardBody>
            <p>
              {__(
                "You can also manage settings using WP-CLI commands:",
                "wordpress-mcp-client"
              )}
            </p>

            <div style={{ display: "grid", gap: "16px", marginTop: "16px" }}>
              <div>
                <h4>{__("Configuration", "wordpress-mcp-client")}</h4>
                <div
                  className="mcp-cli-commands"
                  style={{
                    backgroundColor: "#f0f0f0",
                    padding: "12px",
                    borderRadius: "4px",
                    fontFamily: "monospace",
                  }}
                >
                  <div style={{ marginBottom: "4px" }}>
                    <code>wp mcp config set openai_api_key sk-your-key</code>
                  </div>
                  <div style={{ marginBottom: "4px" }}>
                    <code>wp mcp config set model gpt-4.1</code>
                  </div>
                  <div style={{ marginBottom: "4px" }}>
                    <code>wp mcp config list</code>
                  </div>
                </div>
              </div>

              <div>
                <h4>{__("Testing & Chat", "wordpress-mcp-client")}</h4>
                <div
                  className="mcp-cli-commands"
                  style={{
                    backgroundColor: "#f0f0f0",
                    padding: "12px",
                    borderRadius: "4px",
                    fontFamily: "monospace",
                  }}
                >
                  <div style={{ marginBottom: "4px" }}>
                    <code>wp mcp test</code>
                  </div>
                  <div style={{ marginBottom: "4px" }}>
                    <code>
                      wp mcp chat "Hello, can you help me with WordPress?"
                    </code>
                  </div>
                </div>
              </div>
            </div>
          </CardBody>
        </Card>
      </Flex>
    </div>
  );
};

export default SettingsApp;
