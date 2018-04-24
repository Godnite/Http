<?php

declare(strict_types=1);

namespace Rancoud\Http\Message;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Rancoud\Http\Message\Factory\StreamFactory;

/**
 * Trait Message.
 */
trait Message
{
    /** @var array */
    protected $headers = [];

    /** @var array */
    protected $headerNames = [];

    /** @var string */
    protected $protocol = '1.1';

    /** @var StreamInterface */
    protected $stream;

    /**
     * @return string
     */
    public function getProtocolVersion(): string
    {
        return $this->protocol;
    }

    /**
     * @param $version
     *
     * @throws InvalidArgumentException
     *
     * @return self
     */
    public function withProtocolVersion($version): self
    {
        $this->validateProtocolVersion($version);

        if ($version === $this->protocol) {
            return $this;
        }

        $new = clone $this;
        $new->protocol = $version;

        return $new;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @param $name
     *
     * @throws InvalidArgumentException
     *
     * @return bool
     */
    public function hasHeader($name): bool
    {
        if (is_string($name) === false) {
            throw new InvalidArgumentException('Header name must be a string');
        }

        return array_key_exists(mb_strtolower($name), $this->headerNames);
    }

    /**
     * @param $name
     *
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function getHeader($name): array
    {
        if (is_string($name) === false) {
            throw new InvalidArgumentException('Header name must be a string');
        }

        $name = mb_strtolower($name);

        if (array_key_exists($name, $this->headerNames) === false) {
            return [];
        }

        $name = $this->headerNames[$name];

        return $this->headers[$name];
    }

    /**
     * @param $name
     *
     * @throws InvalidArgumentException
     *
     * @return string
     */
    public function getHeaderLine($name): string
    {
        if (is_string($name) === false) {
            throw new InvalidArgumentException('Header name must be a string');
        }

        return implode(', ', $this->getHeader($name));
    }

    /**
     * @param $name
     * @param $value
     *
     * @throws InvalidArgumentException
     *
     * @return self
     */
    public function withHeader($name, $value): self
    {
        if (is_string($name) === false || mb_strlen($name) <= 0) {
            throw new InvalidArgumentException('Header name must be non-empty string');
        }

        if (is_array($value) === false) {
            $value = [$value];
        } elseif (count($value) <= 0) {
            throw new InvalidArgumentException('Header values must be non-empty strings');
        }

        foreach ($value as $v) {
            if (is_string($v) === false || mb_strlen($v) <= 0) {
                throw new InvalidArgumentException('Header values must be non-empty strings');
            }
        }

        $value = $this->trimHeaderValues($value);
        $normalized = mb_strtolower($name);

        $new = clone $this;
        if (isset($new->headerNames[$normalized])) {
            unset($new->headers[$new->headerNames[$normalized]]);
        }
        $new->headerNames[$normalized] = $name;
        $new->headers[$name] = $value;

        return $new;
    }

    /**
     * @param $name
     * @param $value
     *
     * @throws InvalidArgumentException
     *
     * @return self
     */
    public function withAddedHeader($name, $value): self
    {
        if (is_string($name) === false || mb_strlen($name) <= 0) {
            throw new InvalidArgumentException('Header name must be non-empty string');
        }

        if (is_array($value) === false) {
            $value = [$value];
        } elseif (count($value) > 0) {
            $value = array_values($value);
        } else {
            throw new InvalidArgumentException('Header values must be non-empty strings');
        }

        foreach ($value as $v) {
            if (is_string($v) === false || mb_strlen($v) <= 0) {
                throw new InvalidArgumentException('Header values must be non-empty strings');
            }
        }

        $value = $this->trimHeaderValues($value);
        $normalized = mb_strtolower($name);

        $new = clone $this;
        if (isset($new->headerNames[$normalized])) {
            $name = $this->headerNames[$normalized];
            $new->headers[$name] = array_merge($this->headers[$name], $value);
        } else {
            $new->headerNames[$normalized] = $name;
            $new->headers[$name] = $value;
        }

        return $new;
    }

    /**
     * @param $name
     *
     * @throws InvalidArgumentException
     *
     * @return $this|Message
     */
    public function withoutHeader($name): self
    {
        if (is_string($name) === false || mb_strlen($name) <= 0) {
            throw new InvalidArgumentException('Header name must be non-empty string');
        }

        $normalized = mb_strtolower($name);

        if (array_key_exists($normalized, $this->headerNames) === false) {
            return $this;
        }

        $name = $this->headerNames[$normalized];

        $new = clone $this;
        unset($new->headers[$name], $new->headerNames[$normalized]);

        return $new;
    }

    /**
     * @throws InvalidArgumentException
     *
     * @return StreamInterface
     */
    public function getBody(): StreamInterface
    {
        if (!$this->stream) {
            $this->stream = (new StreamFactory())->createStream('');
        }

        return $this->stream;
    }

    /**
     * @param StreamInterface $body
     *
     * @return self
     */
    public function withBody(StreamInterface $body): self
    {
        if ($body === $this->stream) {
            return $this;
        }

        $new = clone $this;
        $new->stream = $body;

        return $new;
    }

    /**
     * @param array $headers
     */
    protected function setHeaders(array $headers): void
    {
        $this->headerNames = $this->headers = [];

        foreach ($headers as $header => $value) {
            if (is_array($value) === false) {
                $value = [$value];
            }

            $value = $this->trimHeaderValues($value);
            $normalized = mb_strtolower($header);
            if (array_key_exists($normalized, $this->headerNames) === true) {
                $header = $this->headerNames[$normalized];
                $this->headers[$header] = array_merge($this->headers[$header], $value);
            } else {
                $this->headerNames[$normalized] = $header;
                $this->headers[$header] = $value;
            }
        }
    }

    /**
     * @param array $values
     *
     * @return array
     */
    protected function trimHeaderValues(array $values): array
    {
        return array_map(function (string $value) {
            return trim($value, " \t");
        }, $values);
    }

    /**
     * @param string $protocolVersion
     *
     * @throws InvalidArgumentException
     *
     * @return string
     */
    protected function validateProtocolVersion(string $protocolVersion): string
    {
        if (in_array($protocolVersion, ['1.0', '1.1'], true) === false) {
            throw new InvalidArgumentException('Protocol Version must be 1.0 or 1.1');
        }

        return $protocolVersion;
    }
}