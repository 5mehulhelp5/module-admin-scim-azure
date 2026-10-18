<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminScimAzure\Test\Unit\Model\Azure;

use Magento\Framework\Escaper;
use DmLab\AdminScim\Model\Discovery\EndpointUrlBuilder;
use DmLab\AdminScimAzure\Model\Azure\ScimSetupInfo;
use PHPUnit\Framework\TestCase;

/**
 * The setup surface must render the actual SCIM endpoint (from the core's URL
 * builder, HTML-escaped) as Entra's Tenant URL, plus the Entra provisioning
 * guidance an admin needs.
 */
class ScimSetupInfoTest extends TestCase
{
    private const ENDPOINT = 'https://magento.example/admin-scim/v2';

    /** @var EndpointUrlBuilder */
    private EndpointUrlBuilder $urlBuilder;

    /** @var Escaper */
    private Escaper $escaper;

    /** @var ScimSetupInfo */
    private ScimSetupInfo $setupInfo;

    protected function setUp(): void
    {
        $this->urlBuilder = $this->createStub(EndpointUrlBuilder::class);
        $this->urlBuilder->method('baseUrl')->willReturn(self::ENDPOINT);

        $this->escaper = $this->createStub(Escaper::class);
        $this->escaper->method('escapeHtml')->willReturnArgument(0);

        $this->setupInfo = new ScimSetupInfo($this->urlBuilder, $this->escaper);
    }

    public function testEndpointUrlComesFromTheCoreBuilder(): void
    {
        self::assertSame(self::ENDPOINT, $this->setupInfo->getEndpointUrl());
    }

    public function testHtmlRendersTheEndpointAsTenantUrl(): void
    {
        $html = $this->setupInfo->getHtml();

        self::assertStringContainsString(self::ENDPOINT, $html);
        self::assertStringContainsString('Tenant URL', $html);
    }

    public function testHtmlEscapesTheEndpointThroughTheEscaper(): void
    {
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(
            static fn ($value) => $value === self::ENDPOINT ? 'ESCAPED_ENDPOINT' : $value
        );

        $html = (new ScimSetupInfo($this->urlBuilder, $escaper))->getHtml();

        self::assertStringContainsString('<code>ESCAPED_ENDPOINT</code>', $html);
        self::assertStringNotContainsString(self::ENDPOINT, $html);
    }

    public function testEveryPhraseIsEscaped(): void
    {
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static fn ($value) => '[' . $value . ']');

        $html = (new ScimSetupInfo($this->urlBuilder, $escaper))->getHtml();

        self::assertStringContainsString('<strong>[Tenant URL:]</strong>', $html);
        self::assertStringContainsString(
            '<em>[Stores → Configuration → DMLab → Admin SCIM → Provider = azure]</em>',
            $html
        );
        $unescaped = preg_replace(['/\[[^\]]*\]/u', '/<[^>]*>/u'], '', $html);
        self::assertMatchesRegularExpression('/^[\s.]*$/u', $unescaped, 'Unescaped text in: ' . $html);
    }

    public function testHtmlDocumentsTheProviderStep(): void
    {
        self::assertStringContainsString(
            'Stores → Configuration → DMLab → Admin SCIM → Provider = azure',
            $this->setupInfo->getHtml()
        );
    }

    public function testHtmlDocumentsSecretTokenAndProvisioningGuidance(): void
    {
        $html = $this->setupInfo->getHtml();

        self::assertStringContainsString('Secret Token', $html);
        self::assertStringContainsString('Bearer Token', $html);
        self::assertStringContainsString('active = "False"', $html);
        self::assertStringContainsString('are ignored', $html);
    }

    public function testHtmlDoesNotClaimANativeFalseDeactivation(): void
    {
        self::assertStringNotContainsString('active = false', $this->setupInfo->getHtml());
    }

    public function testHtmlDocumentsTheHardDelete(): void
    {
        self::assertStringContainsString(
            'a hard delete in Entra sends DELETE /Users/{id}, which hard-deletes the SCIM-managed admin.',
            $this->setupInfo->getHtml()
        );
    }

    public function testHtmlDocumentsThePatchResponse(): void
    {
        self::assertStringContainsString('PATCH is answered with 200', $this->setupInfo->getHtml());
    }
}
