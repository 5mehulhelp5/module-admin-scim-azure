<?php
/**
 * Copyright © DMLab. All rights reserved.
 * Licensed under the Open Software License version 3.0 (OSL-3.0). See LICENSE.
 */
declare(strict_types=1);

namespace DmLab\AdminScimAzure\Model\Azure;

use Magento\Framework\Escaper;
use Magento\Framework\Phrase;
use DmLab\AdminScim\Model\Discovery\EndpointUrlBuilder;

/**
 * Renders the admin setup surface for wiring an Entra ID enterprise-app
 * provisioning connection to this store.
 *
 * Entra needs the **Tenant URL** (the SCIM base URL from the core's
 * {@see EndpointUrlBuilder}) and the **Secret Token** (the admin-scim bearer
 * token); Magento needs the provider set to `azure`. The rest is fixed guidance
 * on what each Entra provisioning action does. Every phrase is translated and
 * escaped. Instantiable without Magento's block stack, so it's unit-testable.
 */
class ScimSetupInfo
{
    /**
     * @param EndpointUrlBuilder $endpointUrlBuilder
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly EndpointUrlBuilder $endpointUrlBuilder,
        private readonly Escaper $escaper
    ) {
    }

    /**
     * The SCIM base URL to paste into Entra's Tenant URL field (e.g. `https://host/admin-scim/v2`).
     */
    public function getEndpointUrl(): string
    {
        return $this->endpointUrlBuilder->baseUrl();
    }

    /**
     * Info-panel HTML for the admin config field: Tenant URL plus Entra provisioning setup steps.
     */
    public function getHtml(): string
    {
        $endpoint = $this->escaper->escapeHtml($this->getEndpointUrl());
        $intro = $this->escape(__('In your Entra ID enterprise application, open Provisioning → Admin Credentials:'));
        $tenantLabel = $this->escape(__('Tenant URL:'));
        $tokenLabel = $this->escape(__('Secret Token:'));
        $token = $this->escape(__(
            'the bearer token from Stores → Configuration → DMLab → Admin SCIM → Bearer Token.'
        ));
        $magentoIntro = $this->escape(__('In Magento, enable Admin SCIM and select this provider:'));
        $provider = $this->escape(__('Stores → Configuration → DMLab → Admin SCIM → Provider = azure'));
        $providerWhy = $this->escape(__('Required when several provider plugins are installed.'));
        $actionsIntro = $this->escape(__('What each Entra provisioning action does:'));
        $createLabel = $this->escape(__('Create:'));
        $create = $this->escape(__('the assigned user becomes a SCIM-managed admin.'));
        $updateLabel = $this->escape(__('Update:'));
        $update = $this->escape(__('userName, externalId, name, emails and active change the admin.'));
        $ignored = $this->escape(__(
            'Other attributes (displayName, title, phone numbers, addresses, enterprise extensions) are ignored.'
        ));
        $deactivateLabel = $this->escape(__('Deactivate:'));
        $deactivate = $this->escape(__(
            'Entra sends active = "False", alone or batched with other changes; the admin is deactivated.'
        ));
        $deleteLabel = $this->escape(__('Delete:'));
        $delete = $this->escape(__(
            'a hard delete in Entra sends DELETE /Users/{id}, which hard-deletes the SCIM-managed admin.'
        ));
        $groupsLabel = $this->escape(__('Groups:'));
        $groups = $this->escape(__('group push is supported; a member remove drops only the listed members.'));
        $response = $this->escape(__('PATCH is answered with 200 and the updated resource, which Entra accepts.'));

        return <<<HTML
<div class="dmlab-admin-scim-azure-setup">
    <p>{$intro}</p>
    <ul>
        <li><strong>{$tenantLabel}</strong> <code>{$endpoint}</code></li>
        <li><strong>{$tokenLabel}</strong> {$token}</li>
    </ul>
    <p>{$magentoIntro} <em>{$provider}</em>. {$providerWhy}</p>
    <p>{$actionsIntro}</p>
    <ul>
        <li><strong>{$createLabel}</strong> {$create}</li>
        <li><strong>{$updateLabel}</strong> {$update} {$ignored}</li>
        <li><strong>{$deactivateLabel}</strong> {$deactivate}</li>
        <li><strong>{$deleteLabel}</strong> {$delete}</li>
        <li><strong>{$groupsLabel}</strong> {$groups}</li>
    </ul>
    <p>{$response}</p>
</div>
HTML;
    }

    /**
     * HTML-escaped rendering of a translated phrase.
     *
     * @param Phrase $phrase
     * @return string
     */
    private function escape(Phrase $phrase): string
    {
        return $this->escaper->escapeHtml($phrase->render());
    }
}
