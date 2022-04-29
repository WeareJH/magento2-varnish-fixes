<?php

declare(strict_types=1);

namespace Trive\Varnish\Test\Unit\Plugin;

use Magento\Framework\HTTP\PhpEnvironment\Response;
use Magento\PageCache\Model\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionProperty;
use Trive\Varnish\Plugin\SplitXMagentoTagsHeader;

class SplitXMagentoTagsHeaderTest extends TestCase
{
    /**
     * @var string
     */
    private const X_MAGENTO_TAGS_HEADER = 'X-Magento-Tags';

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var SplitXMagentoTagsHeader
     */
    private $splitXMagentoTagsHeader;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var callable
     */
    private $callable;

    /**
     * @var string
     */
    private $initialXMagentoTagsValueInsideLimit;

    /**
     * @var string
     */
    private $initialXMagentoTagsValueOutsideOfLimit;

    /**
     * @return void
     * @throws ReflectionException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->splitXMagentoTagsHeader = new SplitXMagentoTagsHeader($this->config);
        $response = new Response();
        $this->response = $response;
        $this->decreaseRequestLimitSizeJustForTesting();
        $this->initialXMagentoTagsValueInsideLimit = 'tag_1,tag_2';
        $this->initialXMagentoTagsValueOutsideOfLimit = 'tag_1,tag_2,tag_3';
        $this->callable = static function ($name, $value, $replace = false) use ($response) {
            $response->setHeader($name, $value, $replace);
        };
    }

    /**
     * @return void
     */
    public function testAroundSetHeaderWhenNotXMagentoTagsIsSet(): void
    {
        $this->splitXMagentoTagsHeader->aroundSetHeader(
            $this->response,
            $this->callable,
            'some_different_header',
            $this->initialXMagentoTagsValueOutsideOfLimit
        );

        $headers = $this->response->getHeaders()->toArray();
        $this->assertArrayNotHasKey(self::X_MAGENTO_TAGS_HEADER, $headers);
    }

    /**
     * @return void
     */
    public function testAroundSetHeaderWhenFullPageCacheIsDisabled(): void
    {
        $this->config->expects($this->any())
            ->method('isEnabled')
            ->willReturn(false);

        $this->splitXMagentoTagsHeader->aroundSetHeader(
            $this->response,
            $this->callable,
            self::X_MAGENTO_TAGS_HEADER,
            $this->initialXMagentoTagsValueOutsideOfLimit
        );

        $headers = $this->response->getHeaders()->toArray();
        $this->assertSame(
            $this->initialXMagentoTagsValueOutsideOfLimit,
            $headers[self::X_MAGENTO_TAGS_HEADER]
        );
    }

    /**
     * @return void
     */
    public function testAroundSetHeaderWhenFullPageIsNotBasedOnVarnish(): void
    {
        $this->expectationsForFpcEnabled();

        $this->config->expects($this->any())
            ->method('getType')
            ->willReturn(Config::BUILT_IN);

        $this->splitXMagentoTagsHeader->aroundSetHeader(
            $this->response,
            $this->callable,
            self::X_MAGENTO_TAGS_HEADER,
            $this->initialXMagentoTagsValueOutsideOfLimit
        );

        $headers = $this->response->getHeaders()->toArray();
        $this->assertSame(
            $this->initialXMagentoTagsValueOutsideOfLimit,
            $headers[self::X_MAGENTO_TAGS_HEADER]
        );
    }

    /**
     * @return void
     */
    public function testAroundSetHeaderWhenXMagentoTagsIsInsideLimit(): void
    {
        $this->expectationsForFpcEnabled();
        $this->expectationsForVarnishUsed();

        $this->splitXMagentoTagsHeader->aroundSetHeader(
            $this->response,
            $this->callable,
            self::X_MAGENTO_TAGS_HEADER,
            $this->initialXMagentoTagsValueInsideLimit
        );

        $headers = $this->response->getHeaders()->toArray();
        $this->assertSame(
            $this->initialXMagentoTagsValueInsideLimit,
            $headers[self::X_MAGENTO_TAGS_HEADER]
        );
    }

    /**
     * @return void
     */
    public function testAroundSetHeaderWhenXMagentoTagsOverflowsLimit(): void
    {
        $this->expectationsForFpcEnabled();
        $this->expectationsForVarnishUsed();

        $this->splitXMagentoTagsHeader->aroundSetHeader(
            $this->response,
            $this->callable,
            self::X_MAGENTO_TAGS_HEADER,
            $this->initialXMagentoTagsValueOutsideOfLimit
        );

        $headers = $this->response->getHeaders()->toArray();
        $this->assertSame(
            [0 => 'tag_1,tag_2', 1 => 'tag_3'],
            $headers[self::X_MAGENTO_TAGS_HEADER]
        );
    }

    /**
     * @return void
     * @throws ReflectionException
     */
    private function decreaseRequestLimitSizeJustForTesting(): void
    {
        $size = new ReflectionProperty($this->splitXMagentoTagsHeader, 'requestSize');
        $size->setAccessible(true);
        $size->setValue($this->splitXMagentoTagsHeader, 11);
    }

    /**
     * @return void
     */
    private function expectationsForFpcEnabled(): void
    {
        $this->config->expects($this->any())
            ->method('isEnabled')
            ->willReturn(true);
    }

    /**
     * @return void
     */
    private function expectationsForVarnishUsed(): void
    {
        $this->config->expects($this->any())
            ->method('getType')
            ->willReturn(Config::VARNISH);
    }
}
