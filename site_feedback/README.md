# Site Feedback

The Site Feedback module provides a reusable feedback experience for Drupal websites.

## Features

- Floating feedback button on non-administrative pages.
- Feedback form with title, rating, type, and message.
- Optional image attachments.
- Full-page screenshot capture with annotation tools.
- Automatic source-page capture.
- Automatic priority, impact, and urgency calculation.
- Administrative feedback listing with filters.
- Feedback detail page with internal administrator notes.
- Configurable feedback-button visibility and high-priority keywords.
- CSRF protection for all state-changing AJAX routes.
- Session ownership checks for uploaded images.

## Requirements

- Drupal 10 or Drupal 11.
- Core File module.

## Installation

Place the module in:

`web/modules/contrib/site_feedback`

Enable **Site Feedback** from Extend, or with Drush:

`drush en site_feedback`

Configure it at:

`/admin/config/development/site-feedback`

## Permissions

The module provides separate permissions for:

- Viewing the feedback list.
- Viewing individual feedback.
- Updating feedback, including status, impact, urgency, and internal notes.
- Deleting feedback.
- Administering module settings.

## Screenshot and uploaded-image behavior

Uploaded screenshots/images are stored in the public `site_feedback` file directory so that the feedback administration UI can display them. Do not use this module for screenshots containing information that must remain private unless the site's file-access policy and storage configuration are adapted accordingly.

Uploaded files are initially temporary. Files become permanent only after they are successfully attached to a submitted feedback entity. Abandoned temporary files can therefore be removed by Drupal's normal temporary-file cleanup.

The module accepts PNG, JPEG/JPG, and WebP images up to 5 MB and validates the actual image content and dimensions before storage.

## Screenshot dependency

The screenshot editor currently uses **html2canvas 1.4.1** as an external JavaScript library loaded from jsDelivr with Subresource Integrity (SRI). The library is MIT licensed. Review the library's license and the site's third-party asset policy before deployment. For a Drupal.org release, review Drupal.org's third-party library requirements and package the library locally if required by the project review.

## Security

State-changing AJAX routes require Drupal CSRF tokens. Uploaded file IDs are bound to the current session, preventing a visitor from attaching or deleting an image uploaded by another visitor. Detailed server-side exception messages are logged rather than returned to the browser.
