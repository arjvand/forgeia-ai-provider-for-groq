<?php

declare(strict_types=1);

namespace Forgeia\GroqAiProvider\Metadata;

use Forgeia\GroqAiProvider\Provider\GroqProvider;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * Class for the Groq model metadata directory.
 *
 * @since 1.0.0
 *
 * @phpstan-type ModelsResponseData array{
 *     data: list<array{id: string, owned_by?: string}>
 * }
 */
class GroqModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory {

	/**
	 * Text generation model allowlist.
	 *
	 * Derived from /openai/v1/models fetch on 2026-06-20.
	 *
	 * @since 1.0.0
	 *
	 * @var list<string>
	 */
	private const TEXT_GENERATION_MODELS = array(
		'deepseek-r1-distill-llama-70b',
		'deepseek-r1-distill-qwen-32b',
		'gemma2-9b-it',
		'gemma-7b-it',
		'llama-3.3-70b-specdec',
		'llama-3.3-70b-versatile',
		'llama-3.1-8b-instant',
		'llama-3.2-1b-preview',
		'llama-3.2-3b-preview',
		'llama-3.2-11b-vision-preview',
		'llama-3.2-90b-vision-preview',
		'llama-guard-3-8b',
		'llama3-70b-8192',
		'llama3-8b-8192',
		'mixtral-8x7b-32768',
		'qwen-2.5-32b',
		'qwen-2.5-coder-32b',
		'qwen-qwq-32b',
		'gemma-2-27b-it',
		'gemma-2-9b-it',
		'deepseek-r1-distill-llama-70b-specdec',
		'llama-4-scout-17b-16e-instruct',
		'llama-4-maverick-17b-128e-instruct',
		'llama-4-maverick-17b-128e-preview',
	);

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = array(), $data = null ): Request {
		return new Request(
			$method,
			GroqProvider::url( $path ),
			$headers,
			$data
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	protected function parseResponseToModelMetadataList( Response $response ): array {
		/** @var ModelsResponseData $responseData */
		$responseData = $response->getData();
		if ( ! isset( $responseData['data'] ) || ! $responseData['data'] ) {
			throw ResponseException::fromMissingData( 'Groq', 'data' );
		}

		// Text generation capabilities and options.
		$textCapabilities = array(
			CapabilityEnum::textGeneration(),
			CapabilityEnum::chatHistory(),
		);
		$textOptions      = array(
			new SupportedOption( OptionEnum::systemInstruction() ),
			new SupportedOption( OptionEnum::maxTokens() ),
			new SupportedOption( OptionEnum::temperature() ),
			new SupportedOption( OptionEnum::topP() ),
			new SupportedOption( OptionEnum::stopSequences() ),
			new SupportedOption( OptionEnum::functionDeclarations() ),
			new SupportedOption( OptionEnum::customOptions() ),
			new SupportedOption( OptionEnum::inputModalities(), array( array( ModalityEnum::text() ) ) ),
			new SupportedOption( OptionEnum::outputModalities(), array( array( ModalityEnum::text() ) ) ),
		);

		$modelsData = (array) $responseData['data'];

		$models = array_values(
			array_filter(
				array_map(
					static function ( array $modelData ) use (
						$textCapabilities,
						$textOptions
					): ?ModelMetadata {
						$modelId = $modelData['id'];

						if ( self::isTextGenerationModel( $modelId ) ) {
							return new ModelMetadata(
								$modelId,
								$modelId,
								$textCapabilities,
								$textOptions
							);
						}

						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log(
							'Unrecognized Groq model ID, skipping: ' . $modelId
						);
						return null;
					},
					$modelsData
				),
				static function ( ?ModelMetadata $model ): bool {
					return $model !== null;
				}
			)
		);

		usort( $models, array( $this, 'modelSortCallback' ) );

		return $models;
	}

	/**
	 * Checks if a model is a text generation model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $modelId The model ID.
	 * @return bool True if the model is a text generation model.
	 */
	private static function isTextGenerationModel( string $modelId ): bool {
		return in_array( $modelId, self::TEXT_GENERATION_MODELS, true );
	}

	/**
	 * Callback function for sorting models by ID, to be used with `usort()`.
	 *
	 * This method expresses preferences for certain models or model families within the provider by putting them
	 * earlier in the sorted list. The objective is not to be opinionated about which models are better, but to ensure
	 * that more commonly used, more recent, or flagship models are presented first to users.
	 *
	 * @since 1.0.0
	 *
	 * @param ModelMetadata $a First model.
	 * @param ModelMetadata $b Second model.
	 * @return int Comparison result.
	 */
	protected function modelSortCallback( ModelMetadata $a, ModelMetadata $b ): int {
		$aId = $a->getId();
		$bId = $b->getId();

		// Prefer LLaMA models.
		$aIsLlama = str_starts_with( $aId, 'llama' );
		$bIsLlama = str_starts_with( $bId, 'llama' );
		if ( $aIsLlama && ! $bIsLlama ) {
			return -1;
		}
		if ( $bIsLlama && ! $aIsLlama ) {
			return 1;
		}

		// Prefer DeepSeek models.
		$aIsDeepSeek = str_starts_with( $aId, 'deepseek' );
		$bIsDeepSeek = str_starts_with( $bId, 'deepseek' );
		if ( $aIsDeepSeek && ! $bIsDeepSeek ) {
			return -1;
		}
		if ( $bIsDeepSeek && ! $aIsDeepSeek ) {
			return 1;
		}

		// Prefer newer model versions (higher version numbers).
		$aVersion = self::extractVersion( $aId );
		$bVersion = self::extractVersion( $bId );
		if ( $aVersion !== null && $bVersion !== null ) {
			if ( version_compare( $aVersion, $bVersion, '>' ) ) {
				return -1;
			}
			if ( version_compare( $bVersion, $aVersion, '>' ) ) {
				return 1;
			}
		}

		// Fallback: Sort alphabetically.
		return strcmp( $aId, $bId );
	}

	/**
	 * Extracts a version number from a model ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $modelId The model ID.
	 * @return string|null The version string, or null if no version found.
	 */
	private static function extractVersion( string $modelId ): ?string {
		if ( preg_match( '/(\d+\.\d+(?:\.\d+)?)/', $modelId, $matches ) ) {
			return $matches[1];
		}
		return null;
	}
}
