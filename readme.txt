=== Forgeia AI Provider for Groq ===
Contributors: arjvand
Tags: ai, groq, artificial-intelligence, connector, lpu
Requires at least: 6.9
Tested up to: 7.0
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds text generation with Groq LPU inference models to the WordPress AI Client.

== Description ==

This plugin provides Groq API integration for the PHP AI Client SDK. It enables WordPress sites to use Groq's ultra-fast Language Processing Units (LPUs) for text generation with open-source models.

**Features:**

* Text generation with Groq LPU inference models
* Function calling (tool use) support
* System instruction support
* Automatic provider registration
* Dynamic model discovery from the Groq API

Available models are dynamically discovered from the Groq API and include LLaMA, DeepSeek, Gemma, Mixtral, Qwen, and other open-source models running on Groq's fast inference infrastructure.

**Requirements:**

* PHP 7.4 or higher
* For WordPress 6.9, the [wordpress/php-ai-client](https://github.com/wordpress/php-ai-client) package must be installed
* For WordPress 7.0 and above, no additional changes are required
* Groq API key

== Supported Models ==

Models are dynamically discovered from the Groq API. The current allowlist includes:

* `deepseek-r1-distill-llama-70b`
* `deepseek-r1-distill-llama-70b-specdec`
* `deepseek-r1-distill-qwen-32b`
* `gemma-2-27b-it`
* `gemma-2-9b-it`
* `gemma-7b-it`
* `gemma2-9b-it`
* `llama-3.1-8b-instant`
* `llama-3.2-1b-preview`
* `llama-3.2-3b-preview`
* `llama-3.2-11b-vision-preview`
* `llama-3.2-90b-vision-preview`
* `llama-3.3-70b-specdec`
* `llama-3.3-70b-versatile`
* `llama-4-maverick-17b-128e-instruct`
* `llama-4-maverick-17b-128e-preview`
* `llama-4-scout-17b-16e-instruct`
* `llama-guard-3-8b`
* `llama3-70b-8192`
* `llama3-8b-8192`
* `mixtral-8x7b-32768`
* `qwen-2.5-32b`
* `qwen-2.5-coder-32b`
* `qwen-qwq-32b`

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/forgeia-ai-provider-for-groq/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your Groq API key via the `GROQ_API_KEY` environment variable or constant

== Frequently Asked Questions ==

= How do I get a Groq API key? =

Visit the [Groq Console](https://console.groq.com/keys) to create an account and generate an API key.

= Does this plugin work without the PHP AI Client? =

No, this plugin requires the PHP AI Client plugin to be installed and activated. It provides the Groq-specific implementation that the PHP AI Client uses.

== Privacy ==

This plugin sends data to the Groq API (https://api.groq.com) when generating text. The data sent is limited to the user's prompt text and API key and is used solely to fulfill the generation request. No data is collected, stored, or transmitted to any other third party.

This plugin does not use cookies, tracking, or analytics. It stores no personal data on your server and sets no cookies. In accordance with GDPR and UK GDPR requirements, no user consent is needed for privacy compliance — all data processing is limited to API request fulfillment with no storage or sharing of personal information.

== Changelog ==

= 1.0.0 =

* Initial release of the plugin
* Support for Groq LPU text generation models
* Function calling (tool use) support
* System instruction support
