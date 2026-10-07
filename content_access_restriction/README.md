# Content Access Restriction

A reusable Drupal module that provides configurable, role-based and user-specific access restrictions for content. It allows administrators to control which content types support restrictions, select user roles and specific usernames that should be restricted from individual content items, choose the response behavior (403 or 404), optionally bypass restrictions for administrators, and review restricted content from a centralized administration page.

## Features

- Configure which Drupal content types support content access restrictions.
- Restrict individual content items from one or more available user roles.
- Restrict individual content items from specific users by entering multiple usernames separated by commas.
- Configure restrictions directly from the content edit form.
- Supports all available Drupal user roles except the `Administrator` role, which is handled separately through the administrator bypass option.
- Choose how restricted content responds:
  - **Access Denied (403)**
  - **Page Not Found (404)**
- Optionally allow users with the `Administrator` role to bypass content restrictions.
- Provides a centralized **Restricted Content** report.
- The restricted content report displays:
  - Content ID
  - Content title
  - Content type
  - Restricted roles
  - Restricted usernames
  - Restriction type
  - Administrator bypass status
  - Last updated date
  - Edit/View operations when the current user has the required permissions
- Uses Drupal's request event system to enforce restrictions on canonical node pages.
- Restriction checks are limited to the configured content types.
- Provides dedicated permissions for administration, settings management, and restricted-content reporting.
- Automatically creates the required node fields when the module is installed.
- Adds the specific-user restriction field to existing installations through a Drupal update hook.
- Removes the module-created fields when the module is uninstalled.

## Requirements

- Drupal 10 or Drupal 11
- Node module (`drupal:node`)

## Installation

Install the module using one of the standard Drupal module installation methods.

### Manual installation

1. Copy the `content_access_restriction` directory into:
   - `web/modules/custom/` for a Composer-based Drupal project, or
   - `modules/custom/` where applicable.
2. Enable the module from **Extend** in the Drupal administration interface.
3. Alternatively, enable it using Drush:

```bash
drush en content_access_restriction -y
```

During installation, the module creates the following node fields:

- `field_restrict_roles`
- `field_restrict_action`
- `field_restrict_bypass_admin`
- `field_restrict_usernames`

These fields are created for the available content types.

### Updating an existing installation

If the module was already installed before the specific-user restriction feature was added, run Drupal database updates after deploying the new module version:

```bash
drush updb -y
```

The update hook creates `field_restrict_usernames` for the available content types without requiring the module to be uninstalled and reinstalled.

## Configuration

After enabling the module, go to:

**Configuration → Content → Content Access Restriction**

The module provides two administration options:

### Settings

Configure the content types for which Content Access Restriction is enabled.

Only selected content types will use the restriction controls and access-check logic.

### Restricted Content

View a centralized list of content that has configured access restrictions.

The report provides information about restricted roles, restricted usernames, restriction response, administrator bypass setting, last updated date, and available operations.

## Configuring a Content Restriction

After enabling Content Access Restriction for a content type:

1. Create or edit a piece of content of the configured content type.
2. Open the **Content Access Restriction** section in the content edit form.
3. Select the user roles from which the content should be restricted.
4. Enter one or more specific usernames in **Restrict from specific users**, separated by commas. For example:

```text
user1, user2, john.doe
```

5. Select the restriction response:
   - **Access Denied (403)** - returns an HTTP 403 response.
   - **Page Not Found (404)** - returns an HTTP 404 response.
6. Optionally enable **Bypass this access check for Administrator**.
7. Save the content.

The restriction is applied when the content is accessed through its canonical node URL.

## Restricting Specific Users

In addition to role-based restrictions, the module supports restricting individual users by username.

Administrators can enter multiple usernames in the **Restrict from specific users** field using comma-separated values:

```text
alice, bob, john.doe
```

The usernames are trimmed and de-duplicated when the content is saved. During access checking, the current Drupal username is compared with the configured usernames.

A user is restricted when either of the following conditions is true:

- The user has a role configured in the restricted-role list.
- The user's username is configured in the restricted-user list.

If either condition matches, the configured 403 or 404 response is applied.

The configured usernames are also displayed in the **Restricted Content** administration report so administrators can see both role-based and user-specific restrictions.

> **Note:** This feature stores usernames, not numeric Drupal user IDs. If a username is changed after it has been configured on a content item, the restriction will need to be updated to use the new username.


## Specific User Restrictions

In addition to role-based restrictions, individual users can be restricted from accessing a content item.

The **Restrict from specific users** field provides autocomplete suggestions while typing:

- Typing a **username** searches for matching Drupal users and suggests the matching username.
- Typing a **numeric user ID (UID)** searches for that UID and suggests the corresponding username.
- Multiple users can be selected and stored as comma-separated usernames.
- Suggestions display the username together with the UID, for example `john.doe (UID: 123)`.
- The autocomplete searches the current token when multiple comma-separated users are being entered.

For example:

```text
john.doe, jane.smith, portal.user
```

Access is restricted when the current user matches a configured restricted username or belongs to a configured restricted role.

The **Restricted Content** report displays the configured specific usernames in the **Restricted Users** column alongside the restricted roles.

The user autocomplete endpoint requires the Drupal **Access user profiles** permission. This prevents users without permission to view user profiles from using the user lookup endpoint.

## How Access Restriction Works

When a user requests a canonical node page, the module checks:

1. Whether the request is a main request.
2. Whether the route is the canonical node route.
3. Whether the requested node belongs to a content type enabled in module settings.
4. Which roles are configured as restricted for the node.
5. Which usernames are configured as restricted for the node.
6. Whether the current user has one of the restricted roles or their username is explicitly restricted.
7. Whether the administrator bypass option applies.
8. Which response behavior is configured.

If the user is restricted:

- A **403 Access Denied** response is returned when `403` is configured.
- A **404 Page Not Found** response is returned when `404` is configured.

## Administrator Bypass

For each restricted content item, administrators can enable:

**Bypass this access check for Administrator**

When enabled, users with the Drupal `administrator` role can access the restricted content even when that role is included in the access-check context.

The `Administrator` role is excluded from the selectable restriction-role list because it is managed through this dedicated bypass setting.

## Permissions

The module provides the following permissions:

| Permission | Purpose |
|---|---|
| `administer content access restriction` | Access the main Content Access Restriction administration page. |
| `administer content access restriction settings` | Configure which content types support restrictions. |
| `view restricted content` | View the Restricted Content report. |

Assign these permissions through:

**People → Permissions**

## Administration Routes

| Function | Path |
|---|---|
| Content Access Restriction overview | `/admin/config/content/content-access-restriction` |
| Settings | `/admin/config/content/content-access-restriction/settings` |
| Restricted Content | `/admin/config/content/content-access-restriction/restricted-content` |

Access to these routes is controlled by the module permissions.

## Module Structure

```text
content_access_restriction/
├── content_access_restriction.info.yml
├── content_access_restriction.install
├── content_access_restriction.links.menu.yml
├── content_access_restriction.module
├── content_access_restriction.permissions.yml
├── content_access_restriction.routing.yml
├── content_access_restriction.services.yml
└── src/
    ├── Controller/
    │   └── ContentAccessRestrictionController.php
    ├── EventSubscriber/
    │   └── ContentAccessSubscriber.php
    └── Form/
        └── ContentAccessRestrictionSettingsForm.php
```

## Technical Overview

The module uses:

- Drupal configuration for storing enabled content types.
- Node fields for storing restriction settings.
- A node form alter to expose restriction controls on supported content types.
- An entity builder to persist the custom restriction values when content is saved.
- A kernel request event subscriber to enforce access restrictions.
- Drupal user roles to determine whether a user should be restricted.
- Configured usernames to determine whether a specific user should be restricted.
- A custom controller to provide the administration overview and restricted-content report.
- Drupal permissions to control access to administration functionality.

### Restriction Fields

| Field | Type | Purpose |
|---|---|---|
| `field_restrict_roles` | List (text) | Stores the user roles restricted from the content. |
| `field_restrict_action` | List (text) | Stores the configured response: `403` or `404`. |
| `field_restrict_bypass_admin` | Boolean | Determines whether the Administrator role bypasses the restriction. |
| `field_restrict_usernames` | String (long) | Stores comma-separated usernames restricted from the content. |

## Uninstallation

When the module is uninstalled, it removes the fields created by the module:

- `field_restrict_roles`
- `field_restrict_action`
- `field_restrict_bypass_admin`
- `field_restrict_usernames`

Before uninstalling the module, ensure that these fields are not required by other custom functionality.

## Use Cases

This module can be useful for Drupal websites and developer portals that need to:

- Restrict selected content from specific user roles.
- Restrict selected content from specific users by username.
- Protect internal or role-specific documentation.
- Hide content from selected audiences without deleting or unpublishing it.
- Return either a 403 or 404 response depending on the project's security or UX requirements.
- Allow administrators to bypass restrictions for operational access.
- Provide administrators with a centralized view of all restricted content.
- Apply access restriction capabilities selectively to specific content types.

## Compatibility

The module is designed for:

- Drupal 10
- Drupal 11

The module is intended to be reusable across Drupal-based websites and portals and can be extended to accommodate additional project-specific access-control requirements.

## Version

Current module version: `1.2.0`
