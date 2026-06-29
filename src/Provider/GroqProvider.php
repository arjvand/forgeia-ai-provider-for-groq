<?php

declare(strict_types=1);

namespace Forgeia\GroqAiProvider\Provider;

use Forgeia\GroqAiProvider\Metadata\GroqModelMetadataDirectory;
use Forgeia\GroqAiProvider\Models\GroqTextGenerationModel;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

/**
 * Class for the Forgeia AI Provider for Groq.
 *
 * @since 1.0.0
 */
class GroqProvider extends AbstractApiProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function baseUrl(): string {
		return 'https://api.groq.com/openai/v1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModel(
		ModelMetadata $modelMetadata,
		ProviderMetadata $providerMetadata
	): ModelInterface {
		$capabilities = $modelMetadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new GroqTextGenerationModel( $modelMetadata, $providerMetadata );
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		throw new RuntimeException(
			'Unsupported model capabilities for "' . $modelMetadata->getId() . '": ' . implode( ', ', $capabilities ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		$providerMetadataArgs = array(
			'groq',
			'Groq',
			ProviderTypeEnum::cloud(),
			'https://console.groq.com/keys',
			RequestAuthenticationMethod::apiKey(),
		);
		// Provider description support was added in 1.2.0.
		if ( version_compare( AiClient::VERSION, '1.2.0', '>=' ) ) {
			// For WordPress, we should translate the description.
			if ( function_exists( '__' ) ) {
				// phpcs:ignore Generic.Files.LineLength.TooLong
				$providerMetadataArgs[] = __( 'Text generation with Groq LPU inference models.', 'forgeia-ai-provider-for-groq' );
			} else {
				$providerMetadataArgs[] = 'Text generation with Groq LPU inference models.';
			}
		}
		// Provider logoPath support was added in 1.3.0.
		if ( version_compare( AiClient::VERSION, '1.3.0', '>=' ) ) {
			$providerMetadataArgs[] = dirname( __DIR__, 2 ) . '/assets/images/groq.png';
		}
		return new ProviderMetadata( ...$providerMetadataArgs );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		// Check valid API access by attempting to list models.
		return new ListModelsApiBasedProviderAvailability(
			static::modelMetadataDirectory()
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new GroqModelMetadataDirectory();
	}
}
