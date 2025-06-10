import { useState, useEffect, useRef } from "@wordpress/element";
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
  TextareaControl,
  SelectControl,
  Notice,
  Spinner,
  Panel,
  PanelBody,
  PanelRow,
  Icon,
} from "@wordpress/components";
import { comment, people, send } from "@wordpress/icons";
import { parseMarkdown } from "../utils/markdown";

const ChatApp = () => {
  const [messages, setMessages] = useState([]);
  const [inputMessage, setInputMessage] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [selectedModel, setSelectedModel] = useState("gpt-4.1");
  const [config, setConfig] = useState(null);
  const [error, setError] = useState(null);
  const messagesEndRef = useRef(null);

  // Auto scroll to bottom when new messages arrive
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages]);

  // Load configuration on mount
  useEffect(() => {
    loadConfig();
  }, []);

  const loadConfig = async () => {
    try {
      const response = await apiFetch({
        url: window.mcpClientAdmin.apiUrl + "?action=mcp_get_config",
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          nonce: window.mcpClientAdmin.nonce,
        }),
        includeCredentials: true,
      });

      if (response.success) {
        setConfig(response.data);
        setSelectedModel(response.data.model || "gpt-4.1");
      }
    } catch (err) {
      console.error("Failed to load config:", err);
    }
  };

  const sendMessage = async () => {
    if (!inputMessage.trim() || isLoading) return;

    const userMessage = {
      id: Date.now(),
      type: "user",
      content: inputMessage,
      timestamp: new Date(),
    };

    setMessages((prev) => [...prev, userMessage]);
    const conversationHistory = [...messages, userMessage];
    setInputMessage("");
    setIsLoading(true);
    setError(null);

    try {
      const history = conversationHistory
        .filter((msg) => msg.type === "user" || msg.type === "assistant")
        .map((msg) => ({
          role: msg.type === "user" ? "user" : "assistant",
          content: msg.content,
        }));

      const response = await apiFetch({
        url: window.mcpClientAdmin.apiUrl + "?action=mcp_chat_message",
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        includeCredentials: true,
        body: JSON.stringify({
          nonce: window.mcpClientAdmin.nonce,
          message: userMessage.content,
          model: selectedModel,
          conversation_history: history,
        }),
      });

      if (response.success) {
        const assistantMessage = {
          id: Date.now() + 1,
          type: "assistant",
          content: response.data.message,
          timestamp: new Date(),
          usage: response.data.usage,
        };
        setMessages((prev) => [...prev, assistantMessage]);
      } else {
        setError(response.data || "Unknown error occurred");
      }
    } catch (err) {
      setError(err.message || "Failed to send message");
    } finally {
      setIsLoading(false);
    }
  };

  const testConnection = async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await apiFetch({
        url: window.mcpClientAdmin.apiUrl + "?action=mcp_test_connection",
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          nonce: window.mcpClientAdmin.nonce,
        }),
        includeCredentials: true,
      });

      if (response.success) {
        const systemMessage = {
          id: Date.now(),
          type: "system",
          content: `✅ Connection test successful! ${response.data.message}`,
          timestamp: new Date(),
        };
        setMessages((prev) => [...prev, systemMessage]);
      } else {
        setError(response.data || "Connection test failed");
      }
    } catch (err) {
      setError(err.message || "Connection test failed");
    } finally {
      setIsLoading(false);
    }
  };

  const clearChat = () => {
    setMessages([]);
    setError(null);
  };

  const handleKeyDown = (event) => {
    if (event.key === "Enter" && (event.ctrlKey || event.metaKey)) {
      event.preventDefault();
      sendMessage();
    }
  };

  return (
    <div className="mcp-chat-app">
      <Flex direction="row" gap={4}>
        <FlexBlock>
          <Card>
            <CardHeader>
              <Flex>
                <FlexBlock>
                  <h2>{__("MCP Chat", "wordpress-mcp-client")}</h2>
                </FlexBlock>
                <FlexItem>
                  <Button
                    variant="secondary"
                    onClick={testConnection}
                    disabled={isLoading}
                  >
                    {__("Test Connection", "wordpress-mcp-client")}
                  </Button>
                </FlexItem>
                <FlexItem>
                  <Button
                    variant="secondary"
                    onClick={clearChat}
                    disabled={isLoading}
                  >
                    {__("Clear Chat", "wordpress-mcp-client")}
                  </Button>
                </FlexItem>
              </Flex>
            </CardHeader>

            <CardBody>
              {config && !config.api_key_configured && (
                <Notice status="warning" isDismissible={false}>
                  {__(
                    "OpenAI API key not configured. Please configure it in the Settings page.",
                    "wordpress-mcp-client"
                  )}
                </Notice>
              )}

              {config && !config.mcp_server_enabled && (
                <Notice status="error" isDismissible={false}>
                  {__(
                    "WordPress MCP plugin is not active. Please install and activate the WordPress MCP plugin.",
                    "wordpress-mcp-client"
                  )}
                </Notice>
              )}

              {error && (
                <Notice status="error" onRemove={() => setError(null)}>
                  {error}
                </Notice>
              )}

              <div
                className="mcp-chat-messages"
                style={{
                  height: "400px",
                  overflowY: "auto",
                  border: "1px solid #ddd",
                  padding: "16px",
                  marginBottom: "16px",
                  backgroundColor: "#f9f9f9",
                }}
              >
                {messages.length === 0 && (
                  <div
                    style={{
                      textAlign: "center",
                      color: "#666",
                      padding: "40px",
                    }}
                  >
                    <Icon icon={comment} size={48} />
                    <p>
                      {__(
                        "Start a conversation with your MCP-enabled AI assistant!",
                        "wordpress-mcp-client"
                      )}
                    </p>
                    <p style={{ fontSize: "14px", color: "#999" }}>
                      {__(
                        "Your AI assistant has access to WordPress content through the internal MCP server.",
                        "wordpress-mcp-client"
                      )}
                    </p>
                  </div>
                )}

                {messages.map((message) => (
                  <div
                    key={message.id}
                    className={`mcp-message mcp-message--${message.type}`}
                    style={{ marginBottom: "16px" }}
                  >
                    <Flex gap={2} align="flex-start">
                      <FlexItem>
                        <Icon
                          icon={message.type === "user" ? people : comment}
                          size={24}
                          style={{
                            color:
                              message.type === "user"
                                ? "#0073aa"
                                : message.type === "system"
                                ? "#46b450"
                                : "#333",
                          }}
                        />
                      </FlexItem>
                      <FlexBlock>
                        <div
                          style={{
                            backgroundColor:
                              message.type === "user"
                                ? "#e1f5fe"
                                : message.type === "system"
                                ? "#f1f8e9"
                                : "#fff",
                            padding: "12px",
                            borderRadius: "8px",
                            border: "1px solid #ddd",
                          }}
                        >
                          <div
                            className={`mcp-message-content ${
                              message.type === "assistant" ? "mcp-markdown" : ""
                            }`}
                            style={{ wordBreak: "break-word" }}
                            dangerouslySetInnerHTML={{
                              __html:
                                message.type === "assistant"
                                  ? parseMarkdown(message.content)
                                  : message.content,
                            }}
                          />
                          {message.usage && (
                            <div
                              style={{
                                marginTop: "8px",
                                fontSize: "12px",
                                color: "#666",
                              }}
                            >
                              {__("Tokens used:", "wordpress-mcp-client")}{" "}
                              {message.usage.total_tokens}
                            </div>
                          )}
                          <div
                            style={{
                              marginTop: "4px",
                              fontSize: "11px",
                              color: "#999",
                            }}
                          >
                            {message.timestamp.toLocaleTimeString()}
                          </div>
                        </div>
                      </FlexBlock>
                    </Flex>
                  </div>
                ))}

                {isLoading && (
                  <div style={{ textAlign: "center", padding: "16px" }}>
                    <Spinner />
                    <p>{__("Thinking...", "wordpress-mcp-client")}</p>
                  </div>
                )}

                <div ref={messagesEndRef} />
              </div>

              <Flex direction="column" gap={2}>
                <TextareaControl
                  value={inputMessage}
                  onChange={setInputMessage}
                  placeholder={__(
                    "Type your message here... (Ctrl/Cmd + Enter to send)",
                    "wordpress-mcp-client"
                  )}
                  rows={3}
                  disabled={isLoading}
                  onKeyDown={handleKeyDown}
                />
                <Flex justify="flex-end">
                  <Button
                    variant="primary"
                    onClick={sendMessage}
                    disabled={
                      isLoading ||
                      !inputMessage.trim() ||
                      !config?.api_key_configured ||
                      !config?.mcp_server_enabled
                    }
                    icon={send}
                  >
                    {isLoading
                      ? __("Sending...", "wordpress-mcp-client")
                      : __("Send Message", "wordpress-mcp-client")}
                  </Button>
                </Flex>
              </Flex>
            </CardBody>
          </Card>
        </FlexBlock>

        <FlexItem style={{ width: "300px" }}>
          <Panel>
            <PanelBody
              title={__("Settings", "wordpress-mcp-client")}
              initialOpen={true}
            >
              <PanelRow>
                <SelectControl
                  label={__("Model", "wordpress-mcp-client")}
                  value={selectedModel}
                  options={[
                    { label: "GPT-4.1", value: "gpt-4.1" },
                    { label: "GPT-4.1-mini", value: "gpt-4.1-mini" },
                    { label: "GPT-4.1-nano", value: "gpt-4.1-nano" },
                  ]}
                  onChange={setSelectedModel}
                />
              </PanelRow>
            </PanelBody>

            {config && (
              <PanelBody
                title={__("Status", "wordpress-mcp-client")}
                initialOpen={false}
              >
                <PanelRow>
                  <div>
                    <p>
                      <strong>{__("API Key:", "wordpress-mcp-client")}</strong>{" "}
                      {config.api_key_configured ? "✅" : "❌"}
                    </p>
                    <p>
                      <strong>
                        {__("MCP Server:", "wordpress-mcp-client")}
                      </strong>{" "}
                      {config.mcp_server_enabled ? "✅" : "❌"}
                    </p>
                    <p>
                      <strong>{__("JWT Auth:", "wordpress-mcp-client")}</strong>{" "}
                      {config.jwt_token_status?.has_token ? "✅" : "❌"}
                    </p>
                    <p>
                      <strong>
                        {__("Current Model:", "wordpress-mcp-client")}
                      </strong>{" "}
                      {selectedModel}
                    </p>
                  </div>
                </PanelRow>
              </PanelBody>
            )}

            <PanelBody
              title={__("WordPress MCP Server", "wordpress-mcp-client")}
              initialOpen={false}
            >
              <PanelRow>
                <div>
                  <strong>
                    {__("Internal WordPress Server", "wordpress-mcp-client")}
                  </strong>
                  <br />
                  <small style={{ color: "#666" }}>
                    {window.location.origin}/wp-json/wp/v2/wpmcp/streamable
                  </small>
                  <br />
                  <small style={{ color: "#999" }}>
                    {__("Authentication:", "wordpress-mcp-client")}{" "}
                    {__("JWT Bearer Token", "wordpress-mcp-client")}
                  </small>
                  <p
                    style={{
                      marginTop: "8px",
                      fontSize: "14px",
                      color: "#666",
                    }}
                  >
                    {__(
                      "Provides access to WordPress content, posts, pages, users, and functionality.",
                      "wordpress-mcp-client"
                    )}
                  </p>
                </div>
              </PanelRow>
            </PanelBody>
          </Panel>
        </FlexItem>
      </Flex>
    </div>
  );
};

export default ChatApp;
