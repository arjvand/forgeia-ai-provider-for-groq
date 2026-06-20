# Forgeia AI Provider for Groq

Forgeia AI Provider for Groq for the [PHP AI Client](https://github.com/wordpress/php-ai-client) SDK. Works as both a Composer package and a WordPress plugin.

Groq offers the fastest LLM inference available (200-500 tok/s via custom LPU hardware). This provider integrates Groq's OpenAI-compatible API into the WordPress AI Client ecosystem.

## Requirements

- PHP 7.4 or higher
- When using with WordPress, requires WordPress 7.0 or higher
    - If using an older WordPress release, the [wordpress/php-ai-client](https://github.com/wordpress/php-ai-client) package must be installed

## Installation

### As a Composer Package

```
composer require forgeia/forgeia-ai-provider-for-groq
```

### As a WordPress Plugin

1. Download the plugin files
2. Upload to `/wp-content/plugins/forgeia-ai-provider-for-groq/`
3. Ensure the PHP AI Client plugin is installed and activated
4. Activate the plugin through the WordPress admin

## Usage

### With WordPress

The provider automatically registers itself with the PHP AI Client on the `init` hook. Simply ensure both plugins are active and configure your API key:

```php
// Set your Groq API key (or use the GROQ_API_KEY environment variable)
putenv('GROQ_API_KEY=your-api-key');

// Use the provider
$result = AiClient::prompt('Hello, world!')
    ->usingProvider('groq')
    ->generateTextResult();
```

### As a Standalone Package

```php
use WordPress\AiClient\AiClient;
use Forgeia\GroqAiProvider\Provider\GroqProvider;

// Register the provider
$registry = AiClient::defaultRegistry();
$registry->registerProvider(GroqProvider::class);

// Set your API key
putenv('GROQ_API_KEY=your-api-key');

// Generate text
$result = AiClient::prompt('Explain quantum computing')
    ->usingProvider('groq')
    ->generateTextResult();

echo $result->toText();
```

## Supported Models

Available models are dynamically discovered from the Groq API. This includes text generation models such as LLaMA (Meta), Gemma (Google), Mixtral (Mistral), DeepSeek, Qwen, and others. See the [Groq Console](https://console.groq.com/) for the full list of available models.

## Configuration

The provider uses the `GROQ_API_KEY` environment variable for authentication. You can set this in your environment or via PHP:

```php
putenv('GROQ_API_KEY=your-api-key');
```

## License

GPL-2.0-or-later
