<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminScimAzure\Test\Unit\Integration;

use PHPUnit\Framework\Assert;

/**
 * The admin-scim 0.2.0 PATCH grammar, encoded inline so the plugin is tested against the
 * locked contract rather than core internals. Shapes the contract does not name fail, and so
 * do the core's value checks: blank names, a userName over 40 chars, non-positive member ids.
 */
class CoreAcceptedPatchGrammar
{
    public const MAPPED = 'mapped';
    public const IGNORED = 'ignored';

    private const CORE_USER_URN = 'urn:ietf:params:scim:schemas:core:2.0:User';
    private const OPS = ['add', 'replace', 'remove'];
    private const ACTIVE_VALUES = ['true', 'True', 'false', 'False', '1', '0'];
    private const USER_MAPPED = ['active', 'username', 'externalid', 'name', 'emails'];
    private const NAME_MAPPED = ['givenname', 'familyname', 'formatted'];
    private const EMAIL_SUB_PATHS = ['emails[type eq "work"].value', 'emails[primary eq true].value'];
    private const USER_UNREMOVABLE = ['username', 'name', 'name.givenname', 'name.familyname', 'emails', 'active'];
    private const PATH_PATTERN = '/^([A-Za-z][\w$-]*)(?:\[([^\[\]]+)\])?(?:\.([A-Za-z][\w$-]*))?$/';
    private const USER_REQUIRED_STRINGS = ['username', 'name.givenname', 'name.familyname'];
    private const USERNAME_MAX_LENGTH = 40;
    private const GROUP_MEMBER_FILTER_PATTERN = '/^members\[value eq "([^"]+)"\]$/i';
    private const GROUP_READ_ONLY = ['id', 'meta', 'schemas'];

    /**
     * Classify a User PatchOp operation, or fail when the core would reject it.
     *
     * @param array<mixed> $op
     * @return string self::MAPPED when it changes the admin, self::IGNORED otherwise
     */
    public static function classifyUserOp(array $op): string
    {
        $verb = self::verb($op);
        $path = $op['path'] ?? null;

        if ($path === null || $path === '') {
            return self::classifyPathless($op);
        }
        if (!is_string($path)) {
            self::reject($op, 'non-string path');
        }
        if (strcasecmp(trim($path), self::CORE_USER_URN) === 0) {
            return self::classifyPathless($op);
        }
        if ($verb !== 'remove' && !array_key_exists('value', $op)) {
            self::reject($op, 'missing value');
        }

        return self::classifyAttribute($op, $path, $op['value'] ?? null, $verb === 'remove');
    }

    /**
     * Fail when the core would reject a Group PatchOp operation.
     *
     * @param array<mixed> $op
     */
    public static function assertGroupOpAccepted(array $op): void
    {
        $verb = self::verb($op);
        $path = $op['path'] ?? null;

        if ($path === null || $path === '') {
            $value = $op['value'] ?? null;
            if (!is_array($value) || $value === [] || array_is_list($value)) {
                self::reject($op, 'path-less value must be an object');
            }
            foreach ($value as $key => $item) {
                $key = strtolower((string)$key);
                if (!in_array($key, self::GROUP_READ_ONLY, true)) {
                    self::assertGroupTarget(['path' => $key, 'value' => $item] + $op, $key, $verb);
                }
            }
            return;
        }
        if (!is_string($path)) {
            self::reject($op, 'non-string path');
        }
        self::assertGroupTarget($op, trim($path), $verb);
    }

    /**
     * @param array<string,mixed> $op
     * @param string $path
     * @param string $verb
     */
    private static function assertGroupTarget(array $op, string $path, string $verb): void
    {
        if (preg_match(self::GROUP_MEMBER_FILTER_PATTERN, $path, $m) === 1) {
            if ($verb !== 'remove') {
                self::reject($op, 'filtered member path is remove-only');
            }
            self::assertMemberId($op, $m[1]);
            return;
        }

        $lower = strtolower($path);
        if ($lower === 'members') {
            $value = $op['value'] ?? null;
            if ($verb === 'remove' && !array_key_exists('value', $op)) {
                return;
            }
            if (!is_array($value) || !array_is_list($value)) {
                self::reject($op, 'members value must be a list');
            }
            foreach ($value as $member) {
                if (!is_array($member)) {
                    self::reject($op, 'member must be an object');
                }
                self::assertMemberId($op, $member['value'] ?? null);
            }
            return;
        }
        if ($lower === 'displayname' || $lower === 'externalid') {
            if ($verb === 'remove' || !is_string($op['value'] ?? null)) {
                self::reject($op, $path . ' must be set to a string');
            }
            if ($lower === 'displayname' && trim($op['value']) === '') {
                self::reject($op, 'displayName must not be blank');
            }
            return;
        }

        self::reject($op, 'unknown Group path');
    }

    /**
     * @param array<string,mixed> $op
     * @return string
     */
    private static function classifyPathless(array $op): string
    {
        $value = $op['value'] ?? null;
        if (!array_key_exists('value', $op)) {
            self::reject($op, 'missing value');
        }
        if (!is_array($value) || $value === [] || array_is_list($value)) {
            self::reject($op, 'path-less value must be an object');
        }
        if (self::verb($op) === 'remove') {
            self::reject($op, 'remove requires a path');
        }

        $class = self::IGNORED;
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                self::reject($op, 'non-string attribute key');
            }
            if (strcasecmp(trim($key), self::CORE_USER_URN) === 0) {
                $nested = self::classifyPathless(['value' => $item] + $op);
            } else {
                $nested = self::classifyAttribute($op, $key, $item, false);
            }
            if ($nested === self::MAPPED) {
                $class = self::MAPPED;
            }
        }

        return $class;
    }

    /**
     * @param array<string,mixed> $op
     * @param string $path
     * @param mixed $value
     * @param bool $isRemove
     * @return string
     */
    private static function classifyAttribute(array $op, string $path, mixed $value, bool $isRemove): string
    {
        $path = trim($path);
        if (stripos($path, self::CORE_USER_URN . ':') === 0) {
            $path = substr($path, strlen(self::CORE_USER_URN) + 1);
        }
        if (stripos($path, 'urn:') === 0) {
            if (preg_match('/^urn:\S*:extension:\S+$/i', $path) !== 1) {
                self::reject($op, 'unrecognized schema URN');
            }
            return self::IGNORED;
        }
        if (preg_match(self::PATH_PATTERN, $path, $m) !== 1) {
            self::reject($op, 'unparseable path');
        }

        $attr = strtolower($m[1]);
        $filter = $m[2] ?? '';
        $sub = strtolower($m[3] ?? '');
        $target = $attr === 'emails' || $sub === '' ? $attr : $attr . '.' . $sub;
        if ($isRemove && in_array($target, self::USER_UNREMOVABLE, true)) {
            self::reject($op, 'mutability');
        }
        if (!in_array($attr, self::USER_MAPPED, true)) {
            return self::IGNORED;
        }

        if ($attr === 'emails') {
            if ($filter === '' && $sub === '') {
                return self::MAPPED;
            }
            foreach (self::EMAIL_SUB_PATHS as $known) {
                if (strcasecmp($path, $known) === 0) {
                    return self::MAPPED;
                }
            }
            self::reject($op, 'unsupported emails sub-path');
        }
        if ($filter !== '') {
            self::reject($op, 'filter on a single-valued attribute');
        }
        if (!$isRemove && in_array($target, self::USER_REQUIRED_STRINGS, true)
            && (!is_string($value) || trim($value) === '')
        ) {
            self::reject($op, $target . ' must be a non-blank string');
        }
        if (!$isRemove && $target === 'username' && mb_strlen($value) > self::USERNAME_MAX_LENGTH) {
            self::reject($op, 'userName too long');
        }
        if ($attr === 'name') {
            return $sub === '' || in_array($sub, self::NAME_MAPPED, true) ? self::MAPPED : self::IGNORED;
        }
        if ($sub !== '') {
            self::reject($op, 'sub-attribute of a simple attribute');
        }
        if ($attr === 'active' && !$isRemove && !is_bool($value)
            && !in_array($value, self::ACTIVE_VALUES, true)
        ) {
            self::reject($op, 'active must be a boolean or a coercible string');
        }

        return self::MAPPED;
    }

    /**
     * @param array<string,mixed> $op
     * @param mixed $id
     */
    private static function assertMemberId(array $op, mixed $id): void
    {
        $valid = is_int($id) ? $id > 0 : is_string($id) && preg_match('/^\d+$/', $id) === 1 && (int)$id > 0;
        if (!$valid) {
            self::reject($op, 'member value must be a positive integer id');
        }
    }

    /**
     * @param array<mixed> $op
     * @return string lower-cased verb
     */
    private static function verb(array $op): string
    {
        if (array_is_list($op)) {
            self::reject($op, 'operation must be an object');
        }
        $verb = $op['op'] ?? null;
        if (!is_string($verb) || !in_array(strtolower($verb), self::OPS, true)) {
            self::reject($op, 'bad op');
        }

        return strtolower($verb);
    }

    /**
     * @param mixed $op
     * @param string $reason
     * @return never
     */
    private static function reject(mixed $op, string $reason): never
    {
        Assert::fail(sprintf(
            'Core would reject (%s): %s',
            $reason,
            json_encode($op, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ));
    }
}
