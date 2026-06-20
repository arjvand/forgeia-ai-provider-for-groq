<?php

declare(strict_types=1);

namespace Forgeia\GroqAiProvider\Models;

use Forgeia\GroqAiProvider\Provider\GroqProvider;
use WordPress\AiClient\Common\Exception\InvalidArgumentException;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use WordPress\AiClient\Tools\DTO\FunctionCall;
use WordPress\AiClient\Tools\DTO\FunctionDeclaration;

/**
 * Class for a Groq text generation model using the Chat Completions API.
 *
 * @since 1.0.0
 *
 * @phpstan-type ToolCallData array{
 *     id: string,
 *     type?: string,
 *     function: array{name: string, arguments?: string}
 * }
 * @phpstan-type MessageData array{
 *     role?: string,
 *     content?: string|array<string, mixed>,
 *     tool_calls?: list<ToolCallData>,
 *     tool_call_id?: string
 * }
 * @phpstan-type ChoiceData array{
 *     index?: int,
 *     message?: MessageData,
 *     finish_reason?: string
 * }
 * @phpstan-type UsageData array{
 *     prompt_tokens?: int,
 *     completion_tokens?: int,
 *     total_tokens?: int
 * }
 * @phpstan-type ResponseData array{
 *     id?: string,
 *     choices?: list<ChoiceData>,
 *     usage?: UsageData
 * }
 */
class GroqTextGenerationModel extends AbstractApiBasedModel implements TextGenerationModelInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 */
	final public function generateTextResult( array $prompt ): GenerativeAiResult {
		$httpTransporter = $this->getHttpTransporter();

		$params = $this->prepareGenerateTextParams( $prompt );

		$request = new Request(
			HttpMethodEnum::POST(),
			GroqProvider::url( 'chat/completions' ),
			array( 'Content-Type' => 'application/json' ),
			$params,
			$this->getRequestOptions()
		);

		// Add authentication credentials to the request.
		$request = $this->getRequestAuthentication()->authenticateRequest( $request );

		// Send and process the request.
		$response = $httpTransporter->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );
		return $this->parseResponseToGenerativeAiResult( $response );
	}

	/**
	 * Prepares the given prompt and the model configuration into parameters for the API request.
	 *
	 * @since 1.0.0
	 *
	 * @param list<Message> $prompt The prompt to generate text for. Either a single message or a list of messages
	 *                              from a chat.
	 * @return array<string, mixed> The parameters for the API request.
	 */
	protected function prepareGenerateTextParams( array $prompt ): array {
		$config = $this->getConfig();

		$params = array(
			'model'    => $this->metadata()->getId(),
			'messages' => $this->prepareMessagesParam( $prompt ),
			'stream'   => false,
		);

		$maxTokens = $config->getMaxTokens();
		if ( $maxTokens !== null ) {
			$params['max_tokens'] = $maxTokens;
		}

		$temperature = $config->getTemperature();
		if ( $temperature !== null ) {
			$params['temperature'] = $temperature;
		}

		$topP = $config->getTopP();
		if ( $topP !== null ) {
			$params['top_p'] = $topP;
		}

		$stopSequences = $config->getStopSequences();
		if ( is_array( $stopSequences ) ) {
			$params['stop'] = $stopSequences;
		}

		$functionDeclarations = $config->getFunctionDeclarations();
		$customOptions        = $config->getCustomOptions();

		if ( is_array( $functionDeclarations ) ) {
			$params['tools'] = $this->prepareToolsParam( $functionDeclarations );
		}

		// Add system instruction as a system message if present.
		$systemInstruction = $config->getSystemInstruction();
		if ( $systemInstruction ) {
			array_unshift(
				$params['messages'],
				array(
					'role'    => 'system',
					'content' => $systemInstruction,
				)
			);
		}

		/*
		 * Any custom options are added to the parameters as well.
		 * This allows developers to pass other options that may be more niche or not yet supported by the SDK.
		 */
		foreach ( $customOptions as $key => $value ) {
			if ( isset( $params[ $key ] ) ) {
				throw new InvalidArgumentException(
					sprintf(
						'The custom option "%s" conflicts with an existing parameter.',
                        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						$key
					)
				);
			}
			$params[ $key ] = $value;
		}

		return $params;
	}

	/**
	 * Prepares the messages parameter for the API request.
	 *
	 * @since 1.0.0
	 *
	 * @param list<Message> $messages The messages to prepare.
	 * @return list<array<string, mixed>> The prepared messages parameter.
	 */
	protected function prepareMessagesParam( array $messages ): array {
		$result = array();
		foreach ( $messages as $message ) {
			$messageData = $this->getMessageData( $message );
			if ( $messageData !== null ) {
				$result[] = $messageData;
			}
		}
		return $result;
	}

	/**
	 * Converts a Message object to a Chat Completions API message.
	 *
	 * @since 1.0.0
	 *
	 * @param Message $message The message to convert.
	 * @return array<string, mixed>|null The message data, or null if the message is empty.
	 */
	protected function getMessageData( Message $message ): ?array {
		$parts = $message->getParts();

		if ( empty( $parts ) ) {
			return null;
		}

		$role       = $message->getRole();
		$roleString = $this->getMessageRoleString( $role );

		// Handle function calls and responses.
		$functionParts = array();
		$contentParts  = array();
		foreach ( $parts as $part ) {
			$type = $part->getType();
			if ( $type->isFunctionCall() ) {
				$functionParts[] = $part;
			} elseif ( $type->isFunctionResponse() ) {
				$functionParts[] = $part;
			} else {
				$contentParts[] = $part;
			}
		}

		// If there are function parts, they must be the only parts in the message.
		if ( ! empty( $functionParts ) && ! empty( $contentParts ) ) {
			throw new InvalidArgumentException(
				'Function calls and responses cannot be mixed with other content in a single message.'
			);
		}

		// Handle function call response (tool role).
		if ( ! empty( $functionParts ) && $functionParts[0]->getType()->isFunctionResponse() ) {
			$functionResponse = $functionParts[0]->getFunctionResponse();
			if ( ! $functionResponse ) {
				return null;
			}
			return array(
				'role'         => 'tool',
				'tool_call_id' => $functionResponse->getId(),
				'content'      => json_encode( $functionResponse->getResponse() ),
			);
		}

		// Handle function call (assistant role with tool_calls).
		if ( ! empty( $functionParts ) && $functionParts[0]->getType()->isFunctionCall() ) {
			$functionCall = $functionParts[0]->getFunctionCall();
			if ( ! $functionCall ) {
				return null;
			}
			return array(
				'role'       => 'assistant',
				'tool_calls' => array(
					array(
						'id'       => $functionCall->getId(),
						'type'     => 'function',
						'function' => array(
							'name'      => $functionCall->getName(),
							'arguments' => json_encode( $functionCall->getArgs() ),
						),
					),
				),
			);
		}

		// Handle regular content.
		$content = '';
		foreach ( $contentParts as $part ) {
			$partData = $this->getMessagePartContent( $part );
			if ( $partData !== null ) {
				$content .= $partData;
			}
		}

		if ( $content === '' ) {
			return null;
		}

		return array(
			'role'    => $roleString,
			'content' => $content,
		);
	}

	/**
	 * Returns the Chat Completions API specific role string for the given message role.
	 *
	 * @since 1.0.0
	 *
	 * @param MessageRoleEnum $role The message role.
	 * @return string The role for the API request.
	 */
	protected function getMessageRoleString( MessageRoleEnum $role ): string {
		if ( $role === MessageRoleEnum::model() ) {
			return 'assistant';
		}
		return 'user';
	}

	/**
	 * Returns the content string for a message part.
	 *
	 * @since 1.0.0
	 *
	 * @param MessagePart $part The message part to get the content for.
	 * @return string|null The content string, or null if the part type is not supported.
	 */
	protected function getMessagePartContent( MessagePart $part ): ?string {
		$type = $part->getType();
		if ( $type->isText() ) {
			return $part->getText();
		}
		// Skip unsupported part types (files, etc.) for now.
		return null;
	}

	/**
	 * Prepares the tools parameter for the API request.
	 *
	 * @since 1.0.0
	 *
	 * @param list<FunctionDeclaration> $functionDeclarations The function declarations.
	 * @return list<array<string, mixed>> The prepared tools parameter.
	 */
	protected function prepareToolsParam( array $functionDeclarations ): array {
		$tools = array();

		foreach ( $functionDeclarations as $functionDeclaration ) {
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => $functionDeclaration->getName(),
					'description' => $functionDeclaration->getDescription(),
					'parameters'  => $functionDeclaration->getParameters(),
				),
			);
		}

		return $tools;
	}

	/**
	 * Parses the response from the API endpoint to a generative AI result.
	 *
	 * @since 1.0.0
	 *
	 * @param Response $response The response from the API endpoint.
	 * @return GenerativeAiResult The parsed generative AI result.
	 */
	protected function parseResponseToGenerativeAiResult( Response $response ): GenerativeAiResult {
		/** @var ResponseData $responseData */
		$responseData = $response->getData();

		if ( ! isset( $responseData['choices'] ) || ! $responseData['choices'] ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'choices' );
		}

		$candidates = array();
		foreach ( $responseData['choices'] as $index => $choice ) {
			if ( ! is_array( $choice ) || array_is_list( $choice ) ) {
				throw ResponseException::fromInvalidData(
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					$this->providerMetadata()->getName(),
                    // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					"choices[{$index}]",
					'The value must be an associative array.'
				);
			}

			$candidate = $this->parseChoiceToCandidate( $choice, $index );
			if ( $candidate !== null ) {
				$candidates[] = $candidate;
			}
		}

		$id = isset( $responseData['id'] ) && is_string( $responseData['id'] ) ? $responseData['id'] : '';

		if ( isset( $responseData['usage'] ) && is_array( $responseData['usage'] ) ) {
			$usage      = $responseData['usage'];
			$tokenUsage = new TokenUsage(
				$usage['prompt_tokens'] ?? 0,
				$usage['completion_tokens'] ?? 0,
				$usage['total_tokens'] ?? ( ( $usage['prompt_tokens'] ?? 0 ) + ( $usage['completion_tokens'] ?? 0 ) )
			);
		} else {
			$tokenUsage = new TokenUsage( 0, 0, 0 );
		}

		// Use any other data from the response as provider-specific response metadata.
		$additionalData = $responseData;
		unset( $additionalData['id'], $additionalData['choices'], $additionalData['usage'] );

		return new GenerativeAiResult(
			$id,
			$candidates,
			$tokenUsage,
			$this->providerMetadata(),
			$this->metadata(),
			$additionalData
		);
	}

	/**
	 * Parses a single choice from the API response into a Candidate object.
	 *
	 * @since 1.0.0
	 *
	 * @param ChoiceData $choice The choice data from the API response.
	 * @param int $index The index of the choice in the choices array.
	 * @return Candidate|null The parsed candidate, or null if the choice should be skipped.
	 */
	protected function parseChoiceToCandidate( array $choice, int $index ): ?Candidate {
		if ( ! isset( $choice['message'] ) || ! is_array( $choice['message'] ) ) {
			return null;
		}

		$messageData  = $choice['message'];
		$finishReason = $this->parseFinishReason( $choice['finish_reason'] ?? 'stop' );

		$parts = array();

		// Handle regular content.
		if ( isset( $messageData['content'] ) && is_string( $messageData['content'] ) ) {
			$parts[] = new MessagePart( $messageData['content'] );
		}

		// Handle tool calls.
		if ( isset( $messageData['tool_calls'] ) && is_array( $messageData['tool_calls'] ) ) {
			foreach ( $messageData['tool_calls'] as $toolCall ) {
				if (
					! isset( $toolCall['id'] ) ||
					! isset( $toolCall['function'] ) ||
					! isset( $toolCall['function']['name'] )
				) {
					continue;
				}

				$args = null;
				if ( isset( $toolCall['function']['arguments'] ) && is_string( $toolCall['function']['arguments'] ) ) {
					$decoded = json_decode( $toolCall['function']['arguments'], true );
					if ( is_array( $decoded ) && count( $decoded ) > 0 ) {
						$args = $decoded;
					}
				}

				$functionCall = new FunctionCall(
					$toolCall['id'],
					$toolCall['function']['name'],
					$args
				);
				$parts[]      = new MessagePart( $functionCall );
			}
		}

		if ( empty( $parts ) ) {
			return null;
		}

		$role    = MessageRoleEnum::model();
		$message = new Message( $role, $parts );

		return new Candidate( $message, $finishReason );
	}

	/**
	 * Parses the finish reason string to a FinishReasonEnum.
	 *
	 * @since 1.0.0
	 *
	 * @param string $finishReason The finish reason string from the API.
	 * @return FinishReasonEnum The finish reason enum.
	 */
	protected function parseFinishReason( string $finishReason ): FinishReasonEnum {
		switch ( $finishReason ) {
			case 'stop':
				return FinishReasonEnum::stop();
			case 'length':
				return FinishReasonEnum::length();
			case 'tool_calls':
				return FinishReasonEnum::toolCalls();
			case 'content_filter':
				return FinishReasonEnum::error();
			default:
				return FinishReasonEnum::stop();
		}
	}
}
