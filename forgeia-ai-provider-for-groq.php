<?php

/**
 * Plugin Name: Forgeia AI Provider for Groq
 * Plugin URI: https://github.com/arjvand/forgeia-ai-provider-for-groq
 * Description: Forgeia AI Provider for Groq for the WordPress AI Client.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 1.0.0
 * Author: Alireza Arjvand
 * Author URI: https://github.com/arjvand
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: forgeia-ai-provider-for-groq
 *
 * @package Forgeia\GroqAiProvider
 */

declare(strict_types=1);

namespace Forgeia\GroqAiProvider;

use Forgeia\GroqAiProvider\Provider\GroqProvider;
use WordPress\AiClient\AiClient;

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Registers the Forgeia AI Provider for Groq with the AI Client.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_provider(): void {
	if ( ! class_exists( AiClient::class ) ) {
		return;
	}

	$registry = AiClient::defaultRegistry();

	if ( $registry->hasProvider( GroqProvider::class ) ) {
		return;
	}

	$registry->registerProvider( GroqProvider::class );
}

add_action( 'init', __NAMESPACE__ . '\\register_provider', 5 );
