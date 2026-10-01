=== AgentPress MCP – Connect Claude, ChatGPT & AI Agents to WordPress & WooCommerce ===
Contributors: 100pixel
Donate link: https://100pixel.com
Tags: mcp, ai, ai agent, chatgpt, woocommerce
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

MCP server for WordPress. Connect Claude, ChatGPT, Cursor & any AI agent to manage posts, media, settings and WooCommerce. No coding needed.

== Description ==

**AgentPress MCP** turns your WordPress site into an **MCP server** (Model Context Protocol), so AI assistants such as **Claude, ChatGPT, Cursor, VS Code Copilot, Gemini CLI and Windsurf** can work on your site for you. Ask them to write and publish blog posts, upload images, fix SEO titles, update WooCommerce products or check today's orders, all from your AI chat.

Setup takes about a minute: create an API key, copy the ready-made connection URL into your AI app, and connect. Every action runs as the WordPress user who owns the key, so an AI agent can never do more than that user is allowed to do.

Built by [100pixel.com](https://100pixel.com).

**Need help connecting the plugin to your AI / LLM?** Contact us: [agentpress@100pixel.com](mailto:agentpress@100pixel.com)

= What AI agents can do =

* **Posts, pages and custom post types:** list, search, read, create, update, schedule and delete; manage categories, tags and custom taxonomies; set featured images and custom fields.
* **Media library:** upload images and files from a URL or base64 data, edit alt text and captions, delete media.
* **WooCommerce:** list, create and update products (prices, sale prices, stock, SKU, categories, images); list orders, read order details, change order status and add order notes.
* **Site settings:** read and update title, tagline, timezone, reading, discussion, media and permalink settings.
* **Plugins and themes:** list, activate and deactivate plugins; switch themes.
* **Users:** list users and read profiles (read-only).
* **WordPress Abilities API:** abilities from other plugins that are marked public for MCP are added automatically (WordPress 6.9+).

= Works with =

* Claude (web, desktop and Claude Code)
* ChatGPT (MCP apps / developer mode)
* Cursor
* VS Code (GitHub Copilot)
* Gemini CLI
* Windsurf
* Any client that supports MCP over Streamable HTTP

= Security first =

* Per-user API keys that you can revoke at any time. Only a SHA-256 hash is used to check them.
* Your latest key is stored encrypted with your site's secret salts, so the admin screen can show your ready-to-use connection URL.
* Administrators choose which roles can create keys, which tool groups AI agents get, and can switch on **read-only mode**.
* Every tool also checks the user's normal WordPress capabilities.
* The site URL, admin email, user registration and default role can never be changed through MCP.

= Privacy =

AgentPress MCP does not send any data to 100pixel.com or any other third party, and it has no tracking. Site data is only sent to the AI apps you connect yourself, when they call the MCP server with your API key. The optional "upload media from URL" tool downloads a file from a URL that you or your AI agent provide.

= Credits =

App logos in the admin screen come from [Simple Icons](https://simpleicons.org/) (CC0 1.0). All product names and logos are trademarks of their respective owners; their use does not imply endorsement.

== Installation ==

1. Upload the plugin zip under **Plugins → Add New → Upload Plugin**, or search for "AgentPress MCP".
2. Activate the plugin.
3. Open **AgentPress** in the admin menu and click **Generate key**.
4. Pick your AI app. Each one comes with step-by-step instructions, and your key is already filled in.
5. Using Claude Code, Cursor, VS Code, Gemini CLI or Windsurf? Copy the ready-made **setup prompt** and paste it into the AI's chat. It connects itself.

**Server URL:** `https://your-site.com/wp-json/agentpress/v1/mcp`

Authenticate in one of these ways:

* `Authorization: Bearer <key>` header (Claude Code, Cursor, VS Code, Gemini CLI, Windsurf)
* `X-API-Key: <key>` header
* Key in the URL: `https://your-site.com/wp-json/agentpress/v1/mcp/<key>`, for Claude web/desktop connectors and ChatGPT, which cannot send custom headers. You can turn this off in the settings.

== Frequently Asked Questions ==

= What is MCP? =

The Model Context Protocol (MCP) is an open standard that lets AI assistants use tools and data from other apps. AgentPress MCP adds an MCP server to WordPress, so any MCP-compatible AI can manage your site.

= How do I connect ChatGPT to WordPress? =

In ChatGPT, open Plugins, click Add, choose "Create MCP app", enter a name and description, choose "No authentication", paste the connection URL from the AgentPress screen, and click Connect.

= How do I connect Claude to WordPress? =

In Claude, open Settings → Connectors → Add custom connector and paste the connection URL from the AgentPress screen. For Claude Code, run the command shown on the AgentPress screen.

= I need help connecting the plugin to my AI / LLM =

Ask in the plugin's support forum, or contact us at agentpress@100pixel.com and we'll help you get connected.

= Can the AI break my site? =

AI agents can only do what the key's WordPress user is allowed to do. For extra safety, create keys for an Editor or Author account, turn off tool groups you don't need, or switch on read-only mode.

= I get "Missing API key" although I send the Authorization header =

Some Apache/CGI hosts strip the Authorization header. Use the `X-API-Key` header instead, or add this line to your `.htaccess`:

`SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`

= I get a 404 =

Make sure the REST API (`https://your-site.com/wp-json/`) is reachable and not blocked by a security plugin, firewall or CDN. With "Plain" permalinks the URL is `https://your-site.com/?rest_route=/agentpress/v1/mcp`. The admin screen always shows the correct URL.

= Does it work with WooCommerce HPOS? =

Yes. AgentPress MCP is compatible with WooCommerce High-Performance Order Storage.

= Can other plugins add tools? =

Yes. Call `MCP100P_Tools::add()` on the `mcp100p_register_tools` action, or register a WordPress Ability with `meta.mcp.public = true`.

== Screenshots ==

1. Connection screen with your ready-to-use server URL and API keys.
2. Step-by-step setup for Claude, ChatGPT, Cursor, VS Code, Gemini and more.
3. Settings: access control, read-only mode and tool groups.

== Changelog ==

= 1.0.0 =
* First release.
* MCP server for WordPress over Streamable HTTP, with per-user API keys.
* Works with Claude, ChatGPT, Cursor, VS Code, Gemini CLI, Windsurf and any MCP client.
* Tools for posts, pages, custom post types, taxonomies, media, users, site settings, plugins, themes, WooCommerce and the WordPress Abilities API.
* Read-only mode, tool groups and role-based access control.
