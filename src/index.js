import { render } from "@wordpress/element";
import ChatApp from "./components/ChatApp";
import SettingsApp from "./components/SettingsApp";
import "./styles/admin.scss";

// Render Chat App
const chatAppElement = document.getElementById("mcp-chat-app");
if (chatAppElement) {
  render(<ChatApp />, chatAppElement);
}

// Render Settings App
const settingsAppElement = document.getElementById("mcp-settings-app");
if (settingsAppElement) {
  render(<SettingsApp />, settingsAppElement);
}
