<?php

namespace Omnimap\Testing;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * An HTTP client that answers from what it was given - a callable of the
 * method, the URL and the options, returning [status, body, headers] - and
 * keeps every request made ($requests): a provider's calls checked with no
 * network.
 */
final class RecordedHttpClient implements HttpClientInterface
{
    /** @var list<array{method: string, url: string, options: array<string, mixed>}> */
    public array $requests = [];

    /** @param \Closure(string, string, array<string, mixed>): array{0: int, 1: string, 2?: array<string, list<string>>} $answer */
    public function __construct(private readonly \Closure $answer)
    {
    }

    /** @param array<string, mixed> $options */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];
        $answer = ($this->answer)($method, $url, $options);

        return new RecordedResponse($answer[0], $answer[1], $answer[2] ?? [], $url);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        throw new \LogicException('RecordedHttpClient does not stream.');
    }

    /** @param array<string, mixed> $options */
    public function withOptions(array $options): static
    {
        return $this;
    }
}
