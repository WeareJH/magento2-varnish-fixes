<?php

declare(strict_types=1);

namespace Trive\Varnish\Model\Http;

use Laminas\Http\Header\MultipleHeaderInterface;
use Laminas\Http\Header\GenericHeader;
use Laminas\Http\Header\HeaderValue;
use Laminas\Http\Header\Exception\InvalidArgumentException;

class XMagentoTags implements MultipleHeaderInterface
{
    /**
     * @var string
     */
    private $value;

    /**
     * XMagentoTags constructor.
     *
     * @param string|null $value
     */
    public function __construct(?string $value = null)
    {
        if ($value) {
            HeaderValue::assertValid($value);
            $this->value = $value;
        }
    }

    /**
     * Create X-Magento-Tags header from a given header line
     *
     * @param string $headerLine
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromString($headerLine): self
    {
        [$name, $value] = GenericHeader::splitHeaderLine($headerLine);

        // check to ensure proper header type for this factory
        if (strtolower($name) !== 'x-magento-tags') {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid header line for X-Magento-Tags string: "%s"',
                    $name
                )
            );
        }

        return new static($value);
    }

    /**
     * @param  array $headers
     * @throws InvalidArgumentException
     * @return string
     */
    public function toStringMultipleHeaders(array $headers): string
    {
        $name = $this->getFieldName();
        $values = [$this->getFieldValue()];
        foreach ($headers as $header) {
            if (!$header instanceof static) {
                throw new InvalidArgumentException(
                    'This method toStringMultipleHeaders was expecting an array of headers of the same type'
                );
            }
            $values[] = $header->getFieldValue();
        }

        return $name . ': ' . implode(',', $values) . "\r\n";
    }

    /**
     * @return string
     */
    public function getFieldName(): string
    {
        return 'X-Magento-Tags';
    }

    /**
     * Get the header value
     *
     * @return string
     */
    public function getFieldValue(): string
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        return 'X-Magento-Tags: ' . $this->getFieldValue();
    }
}
