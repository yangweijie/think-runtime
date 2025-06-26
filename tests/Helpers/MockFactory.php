<?php

declare(strict_types=1);

namespace Think\Runtime\Tests\Helpers;

/**
 * Factory for creating mock objects used in tests.
 */
class MockFactory
{
    /**
     * Create a mock ThinkPHP Request object.
     */
    public static function createRequest(array $data = []): object
    {
        $defaults = [
            'method' => 'GET',
            'uri' => '/',
            'query' => [],
            'body' => [],
            'headers' => [],
            'files' => [],
            'cookies' => [],
        ];
        
        $data = array_merge($defaults, $data);
        
        return new class($data) {
            private array $data;
            
            public function __construct(array $data)
            {
                $this->data = $data;
            }
            
            public function method(): string
            {
                return $this->data['method'];
            }
            
            public function uri(): string
            {
                return $this->data['uri'];
            }
            
            public function get(): array
            {
                return $this->data['query'];
            }
            
            public function post(): array
            {
                return $this->data['body'];
            }
            
            public function header(): array
            {
                return $this->data['headers'];
            }
            
            public function file(): array
            {
                return $this->data['files'];
            }
            
            public function cookie(): array
            {
                return $this->data['cookies'];
            }
            
            public function host(): string
            {
                return $this->data['headers']['host'] ?? 'localhost';
            }
            
            public function queryString(): string
            {
                return http_build_query($this->data['query']);
            }
        };
    }

    /**
     * Create a mock ThinkPHP Response object.
     */
    public static function createResponse(array $data = []): object
    {
        $defaults = [
            'status' => 200,
            'headers' => [],
            'content' => 'Hello World',
        ];
        
        $data = array_merge($defaults, $data);
        
        return new class($data) {
            private array $data;
            
            public function __construct(array $data)
            {
                $this->data = $data;
            }
            
            public function getStatusCode(): int
            {
                return $this->data['status'];
            }
            
            public function getHeaders(): array
            {
                return $this->data['headers'];
            }
            
            public function getContent(): string
            {
                return $this->data['content'];
            }
            
            public function send(): void
            {
                echo $this->data['content'];
            }
            
            public function __toString(): string
            {
                return $this->data['content'];
            }
        };
    }

    /**
     * Create a mock Workerman Request object.
     */
    public static function createWorkermanRequest(array $data = []): object
    {
        $defaults = [
            'method' => 'GET',
            'uri' => '/',
            'query' => [],
            'body' => [],
            'headers' => [],
            'files' => [],
            'cookies' => [],
        ];
        
        $data = array_merge($defaults, $data);
        
        return new class($data) {
            private array $data;
            
            public function __construct(array $data)
            {
                $this->data = $data;
            }
            
            public function method(): string
            {
                return $this->data['method'];
            }
            
            public function uri(): string
            {
                return $this->data['uri'];
            }
            
            public function queryString(): string
            {
                return http_build_query($this->data['query']);
            }
            
            public function host(): string
            {
                return $this->data['headers']['host'] ?? 'localhost';
            }
            
            public function header(string $name = null)
            {
                if ($name === null) {
                    return $this->data['headers'];
                }
                return $this->data['headers'][$name] ?? null;
            }
            
            public function get(): array
            {
                return $this->data['query'];
            }
            
            public function post(): array
            {
                return $this->data['body'];
            }
            
            public function file(): array
            {
                return $this->data['files'];
            }
            
            public function cookie(): array
            {
                return $this->data['cookies'];
            }
        };
    }

    /**
     * Create a mock PSR-7 ServerRequest object.
     */
    public static function createPsr7Request(array $data = []): object
    {
        $defaults = [
            'method' => 'GET',
            'uri' => 'http://localhost/',
            'query' => [],
            'body' => '',
            'headers' => [],
        ];
        
        $data = array_merge($defaults, $data);
        
        return new class($data) {
            private array $data;
            
            public function __construct(array $data)
            {
                $this->data = $data;
            }
            
            public function getMethod(): string
            {
                return $this->data['method'];
            }
            
            public function getUri(): object
            {
                return new class($this->data['uri']) {
                    private string $uri;
                    
                    public function __construct(string $uri)
                    {
                        $this->uri = $uri;
                    }
                    
                    public function getPath(): string
                    {
                        return parse_url($this->uri, PHP_URL_PATH) ?: '/';
                    }
                    
                    public function getQuery(): string
                    {
                        return parse_url($this->uri, PHP_URL_QUERY) ?: '';
                    }
                    
                    public function getHost(): string
                    {
                        return parse_url($this->uri, PHP_URL_HOST) ?: 'localhost';
                    }
                    
                    public function getPort(): ?int
                    {
                        return parse_url($this->uri, PHP_URL_PORT);
                    }
                    
                    public function getScheme(): string
                    {
                        return parse_url($this->uri, PHP_URL_SCHEME) ?: 'http';
                    }
                };
            }
            
            public function getHeaders(): array
            {
                return $this->data['headers'];
            }
            
            public function getHeaderLine(string $name): string
            {
                $headers = $this->data['headers'];
                return isset($headers[$name]) ? implode(', ', (array) $headers[$name]) : '';
            }
            
            public function hasHeader(string $name): bool
            {
                return isset($this->data['headers'][$name]);
            }
            
            public function getBody(): object
            {
                return new class($this->data['body']) {
                    private string $body;
                    
                    public function __construct(string $body)
                    {
                        $this->body = $body;
                    }
                    
                    public function __toString(): string
                    {
                        return $this->body;
                    }
                };
            }
        };
    }
}
