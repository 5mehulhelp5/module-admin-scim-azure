<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminScimAzure\Test\Unit\Integration;

use DmLab\AdminScim\Api\RequestNormalizerInterface;
use DmLab\AdminScimAzure\Model\Normalization\AzureRequestNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Entra provisioning lifecycle (create → update → deactivate → group add/remove) through
 * {@see AzureRequestNormalizer}, pinning what reaches the core for each Entra shape.
 */
class CompatibilityMatrixTest extends TestCase
{
    private const ENTERPRISE = 'urn:ietf:params:scim:schemas:extension:enterprise:2.0:User';

    /** @var AzureRequestNormalizer */
    private AzureRequestNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new AzureRequestNormalizer();
    }

    public function testCreatePassesFlatEnterpriseAttributeThrough(): void
    {
        $payload = [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User', self::ENTERPRISE],
            'userName' => 'jane.doe@contoso.com',
            'active' => true,
            self::ENTERPRISE . ':department' => 'Engineering',
        ];

        self::assertSame(
            $payload,
            $this->normalizer->normalize(RequestNormalizerInterface::RESOURCE_USER, 'POST', $payload)
        );
    }

    /**
     * @return array<string,array{string}>
     */
    public static function methodProvider(): array
    {
        return ['POST' => ['POST'], 'PUT' => ['PUT'], 'PATCH' => ['PATCH']];
    }

    #[DataProvider('methodProvider')]
    public function testEmptyBodiesAreReturnedVerbatim(string $method): void
    {
        self::assertSame([], $this->normalizer->normalize(RequestNormalizerInterface::RESOURCE_USER, $method, []));
    }

    /**
     * @return array<string,array{string}>
     */
    public static function verbProvider(): array
    {
        return ['Add' => ['Add'], 'Replace' => ['Replace']];
    }

    #[DataProvider('verbProvider')]
    public function testUpdateViaFlatEnterprisePatchPassesThrough(string $verb): void
    {
        $payload = ['Operations' => [[
            'op' => $verb,
            'path' => self::ENTERPRISE . ':department',
            'value' => 'Platform',
        ]]];

        self::assertSame(
            $payload,
            $this->normalizer->normalize(RequestNormalizerInterface::RESOURCE_USER, 'PATCH', $payload)
        );
    }

    public function testDeactivateViaStringFalsePassesThroughUnchanged(): void
    {
        // Entra's default deactivate; the core coerces the string itself.
        $payload = ['Operations' => [['op' => 'Replace', 'path' => 'active', 'value' => 'False']]];

        self::assertSame(
            $payload,
            $this->normalizer->normalize(RequestNormalizerInterface::RESOURCE_USER, 'PATCH', $payload)
        );
    }

    public function testGroupMemberAddIsStandardAndPassesThrough(): void
    {
        $payload = ['Operations' => [['op' => 'Add', 'path' => 'members', 'value' => [['value' => '42']]]]];

        self::assertSame(
            $payload,
            $this->normalizer->normalize(RequestNormalizerInterface::RESOURCE_GROUP, 'PATCH', $payload)
        );
    }

    public function testGroupMemberRemoveTouchesOnlyTheNamedMembers(): void
    {
        $result = $this->normalizer->normalize(
            RequestNormalizerInterface::RESOURCE_GROUP,
            'PATCH',
            ['Operations' => [[
                'op' => 'Remove',
                'path' => 'members',
                'value' => [['value' => '42'], ['value' => '77']],
            ]]]
        );

        self::assertSame(
            [
                ['op' => 'remove', 'path' => 'members[value eq "42"]'],
                ['op' => 'remove', 'path' => 'members[value eq "77"]'],
            ],
            $result['Operations']
        );
    }

    public function testStandardRfcCreateIsUntouched(): void
    {
        $payload = [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'userName' => 'jane.doe@contoso.com',
            'active' => true,
            'emails' => [['value' => 'jane@contoso.com', 'primary' => true]],
        ];

        self::assertSame(
            $payload,
            $this->normalizer->normalize(RequestNormalizerInterface::RESOURCE_USER, 'POST', $payload)
        );
    }
}
