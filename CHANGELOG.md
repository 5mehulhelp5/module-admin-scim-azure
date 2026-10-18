# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.2.0]

- Normalizer reduced to the core-URN strip and the group-member remove rewrite; extension shapes pass through unchanged. An empty member-remove `value` list is dropped.
- Provider code pinned as `azure` (`AzureRequestNormalizer::PROVIDER_CODE`). The normalizer now runs only when **Admin SCIM → Provider = azure**, or when it is the only provider plugin installed — set the provider after upgrading if several are installed.
- Entra default-mapping fixtures checked against the core PATCH grammar.
- Requires `dmlab/module-admin-scim ^0.2.0`.
- Setup panel and README corrected: provider step, `active: "False"`, mapped vs ignored attributes, hard delete.
- Packaging: declared Magento dependencies, OSL headers, `i18n/`, `CONTRIBUTING.md`, `.gitattributes`.

## [0.1.1]

- Requires `dmlab/module-admin-scim ^0.1.1`.

## [0.1.0]

- Initial release: Entra request normalizer and the Entra setup panel.
