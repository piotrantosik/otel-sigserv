<?php

use App\Kernel;
use OpenTelemetry\Contrib\Otlp\SpanExporterFactory;
use OpenTelemetry\SDK\Common\Export\Http\PsrTransportFactory;
use OpenTelemetry\SDK\Sdk;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessorBuilder;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use function OpenTelemetry\Instrumentation\hook;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

$httpClient = new Psr18Client(
    HttpClient::create(
        defaultOptions: [
            'max_duration' => 1,
        ]
    )
);

$spanExporter = (new SpanExporterFactory(new PsrTransportFactory(
    client: $httpClient, requestFactory: $httpClient, streamFactory: $httpClient
)))->create();

$tracerProvider = TracerProvider::builder()
    ->addSpanProcessor(
        (new BatchSpanProcessorBuilder($spanExporter))
            ->build()
    )
    ->build();

Sdk::builder()
    ->setTracerProvider($tracerProvider)
    ->setAutoShutdown(true)
    ->buildAndRegisterGlobal();

hook(
    HttpClientInterface::class,
    'request',
    pre: static function (
        HttpClientInterface $client,
        array $params,
        string $class,
        string $function,
        ?string $filename,
        ?int $lineno,
    ) {
    },
    post: static function (
        HttpClientInterface $client,
        array $params,
        ?ResponseInterface $response,
        ?\Throwable $exception,
    ): void {
        if (null !== $response && 0 !== $response->getStatusCode()) {
            //sigserv here, see php container log
        }
    },
);

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
