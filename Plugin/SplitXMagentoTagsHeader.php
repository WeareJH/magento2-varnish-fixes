<?php

declare(strict_types=1);

namespace Trive\Varnish\Plugin;

use Magento\Framework\HTTP\PhpEnvironment\Response;
use Magento\PageCache\Model\Config;
use Trive\Varnish\Model\Http\XMagentoTags;
use Laminas\Http\HeaderLoader;

use function strlen;

class SplitXMagentoTagsHeader
{
    /**
     * @var string
     */
    private const X_MAGENTO_TAGS_HEADER = 'X-Magento-Tags';

    /**
     * Approximately 8kb in length
     *
     * @var int
     */
    private $requestSize = 8000;

    /**
     * @var Config
     */
    private $config;

    /**
     * HttpResponseSplitHeader constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Special case for handling X-Magento-Tags header
     * splits very long header into multiple headers
     *
     * @param Response $subject
     * @param callable $proceed
     * @param string $name
     * @param string $value
     * @param bool $replace
     *
     * @return Response|mixed
     * @SuppressWarnings(PHPMD.LongVariable)
     */
    public function aroundSetHeader(Response $subject, callable $proceed, $name, $value, $replace = false)
    {
        if (!$this->isXMagentoTagsSet($name) || !$this->isFpcEnabled() || !$this->isVarnishUsedForFpc()) {
            return $proceed($name, $value, $replace);
        }

        $tags = (string) $value;
        $headerLength = strlen($tags);

        if ($this->isHeaderWithinLimit($headerLength)) {
            return $proceed($name, $value, $replace);
        }

        $this->addMultipleXMagentoTagsAHeaderToStaticMap();

        while ($headerLength > $this->requestSize) {
            $tagSliceLength = strrpos($tags, ',', $this->requestSize - $headerLength);
            $tagsSlice = substr($tags, 0, $tagSliceLength);
            $subject->getHeaders()->addHeaderLine($name, $tagsSlice);
            $tags = substr($tags, $tagSliceLength + 1);
            $headerLength = strlen($tags);
        }

        $subject->getHeaders()->addHeaderLine($name, $tags);
        return $subject;
    }

    /**
     * @return bool
     */
    private function isFpcEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * @return void
     */
    private function isVarnishUsedForFpc(): bool
    {
        return (int) $this->config->getType() === Config::VARNISH;
    }

    /**
     * @param string $name
     * @return bool
     */
    private function isXMagentoTagsSet(string $name): bool
    {
        return $name === self::X_MAGENTO_TAGS_HEADER;
    }

    /**
     * @param int $headerLength
     * @return bool
     */
    private function isHeaderWithinLimit(int $headerLength): bool
    {
        return $headerLength <= $this->requestSize;
    }

    /**
     * @return void
     */
    private function addMultipleXMagentoTagsAHeaderToStaticMap(): void
    {
        HeaderLoader::addStaticMap(
            [
                'xmagentotags' => XMagentoTags::class,
            ]
        );
    }
}
