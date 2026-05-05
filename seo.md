# SEO System Documentation

## Purpose

This file documents the current SEO system in the project:

- what has been implemented
- what has not been implemented yet
- why each part exists
- where the code lives

This is intended as an internal technical reference for future maintenance.

## Current Goal of the SEO Module

The current SEO implementation is designed to give each tenant control over:

- page titles
- meta descriptions
- canonical URLs
- Open Graph / social sharing data
- hreflang alternates
- basic schema.org output
- SEO previews and audits inside the admin panel

The system is intentionally focused on practical tenant-managed SEO, not on full crawler-style SEO automation.

## High-Level Architecture

The SEO system currently has 4 main layers:

1. Data storage in `tenant_site_settings`
2. SEO resolution in PHP via `TenantSeoResolver`
3. Frontend meta rendering via `SeoHead.vue`
4. Admin management screens for editing and auditing SEO

## Data Storage

### What was added

SEO settings are stored inside `tenant_site_settings` under the `seo` key.

Migration:

- [2026_05_04_130000_add_seo_to_tenant_site_settings_table.php](C:/laragon/www/real-rent-car-main/database/migrations/2026_05_04_130000_add_seo_to_tenant_site_settings_table.php)

### Why

This keeps SEO tenant-specific and allows each tenant to control public-site SEO without affecting other tenants.

### Stored structure

The current structure includes:

- `seo.defaults.title_suffix`
- `seo.defaults.default_description`
- `seo.defaults.og_image`
- `seo.defaults.robots`
- `seo.pages.home`
- `seo.pages.fleet`
- `seo.pages.about`
- `seo.pages.contact`
- `seo.pages.car`
- `seo.pages.booking_checkout`
- `seo.pages.booking_confirmation`

Each page currently supports:

- localized `title`
- localized `description`
- `canonical_url`

## SEO Resolver

Main file:

- [TenantSeoResolver.php](C:/laragon/www/real-rent-car-main/app/Support/TenantSeoResolver.php)

### What it does

This class converts tenant SEO settings into final runtime SEO payloads.

It currently supports:

- `forPage()` for general public pages
- `forCar()` for car detail pages
- `forReservation()` for booking-related pages

### Why it exists

Without a resolver, SEO logic would be scattered across controllers and views.

The resolver centralizes:

- fallback logic
- localization fallback
- canonical cleanup
- robots fallback
- OG image fallback
- schema generation
- breadcrumb generation

### Current schema support

The resolver currently generates schema.org payloads for:

- `Organization` / `AutoRental`
- `WebSite`
- `CollectionPage`
- `AboutPage`
- `ContactPage`
- `Product`
- `Reservation`
- `BreadcrumbList`

### Current hreflang behavior

The resolver currently includes:

- all enabled locales
- `x-default`

## Meta Tag Rendering

Frontend renderer:

- [SeoHead.vue](C:/laragon/www/real-rent-car-main/resources/js/components/SeoHead.vue)

### What it renders

When a page receives a `seo` payload, this component renders:

- `<title>`
- `<meta name="description">`
- `<meta name="robots">`
- `<meta property="og:title">`
- `<meta property="og:description">`
- `<meta property="og:image">`
- `<link rel="canonical">`
- `<link rel="alternate" hreflang="...">`
- JSON-LD schemas

### Why it exists

This keeps the head/meta rendering consistent across all pages and avoids duplicating meta-tag logic in every page component.

## Pages Currently Covered

### Public pages

Handled in:

- [HomePagesController.php](C:/laragon/www/real-rent-car-main/app/Http/Controllers/HomePagesController.php)

Currently covered:

- home page
- fleet page
- about page
- contact page
- super admin landing page

Frontend pages using `SeoHead`:

- [Welcome.vue](C:/laragon/www/real-rent-car-main/resources/js/pages/Welcome.vue)
- [Fleet.vue](C:/laragon/www/real-rent-car-main/resources/js/pages/Fleet.vue)
- [About.vue](C:/laragon/www/real-rent-car-main/resources/js/pages/About.vue)
- [Contact.vue](C:/laragon/www/real-rent-car-main/resources/js/pages/Contact.vue)
- [Landing.vue](C:/laragon/www/real-rent-car-main/resources/js/pages/SuperAdmin/landing/Landing.vue)

### Car page

Handled in:

- [BookingController.php](C:/laragon/www/real-rent-car-main/app/Http/Controllers/BookingController.php)

Covered:

- car details page

Why:

- car pages need product-style SEO
- car pages can use car image as OG image

### Booking pages

Handled in:

- [BookingController.php](C:/laragon/www/real-rent-car-main/app/Http/Controllers/BookingController.php)

Covered:

- booking page
- booking checkout page
- booking confirmation page

Frontend pages:

- [Booking.vue](C:/laragon/www/real-rent-car-main/resources/js/pages/Booking.vue)
- [BookingCheckout.vue](C:/laragon/www/real-rent-car-main/resources/js/pages/BookingCheckout.vue)
- [BookingConfirmation.vue](C:/laragon/www/real-rent-car-main/resources/js/pages/BookingConfirmation.vue)

Important current behavior:

- booking checkout and booking confirmation are treated as `noindex,nofollow` by default

Why:

- these are operational pages, not landing pages for search indexing

## Admin SEO Management

Controller:

- [WebsiteSettingsController.php](C:/laragon/www/real-rent-car-main/app/Http/Controllers/Admin/WebsiteSettingsController.php)

Routes:

- [admin.php](C:/laragon/www/real-rent-car-main/routes/admin.php)

Current routes:

- `GET /admin/settings/website`
- `PUT /admin/settings/website`
- `GET /admin/settings/seo`
- `PUT /admin/settings/seo`
- `GET /admin/settings/seo-audit`

### General Settings page

File:

- [Website.vue](C:/laragon/www/real-rent-car-main/resources/js/pages/Admin/Settings/Website.vue)

Current status:

- SEO is no longer edited inside the general settings form
- the page now only contains links/buttons to:
  - SEO Settings
  - SEO Audit

Why:

- the old general settings page became too large
- SEO needed its own workflow and its own validation

### Dedicated SEO Settings page

File:

- [SeoSettings.vue](C:/laragon/www/real-rent-car-main/resources/js/pages/Admin/Settings/SeoSettings.vue)

What it currently includes:

- SEO defaults form
- per-page SEO form fields
- search preview
- Open Graph preview
- Twitter / X card preview
- overall SEO status
- TXT export
- rendered meta tags section

### Why this page exists

This is now the main tenant-facing SEO editing page.

It exists to:

- keep SEO editing separate from brand/content settings
- make previews easier to read
- make SEO validation more focused

## SEO Audit Page

File:

- [SeoAudit.vue](C:/laragon/www/real-rent-car-main/resources/js/pages/Admin/Settings/SeoAudit.vue)

### What it currently includes

- overall SEO health status
- per-page audit cards
- title-length checks
- description-length checks
- OG image checks
- canonical checks
- slug checks
- hreflang checks
- warning if a public page is set to `noindex`
- current-vs-recommended tag comparison
- export as TXT
- export as CSV
- export as JSON

### Why it exists

The edit page is for writing SEO.

The audit page is for reviewing SEO quality and exporting reports.

This separation keeps editing and auditing from becoming one overloaded page.

## Rendered Meta Tags Section

File:

- [SeoSettings.vue](C:/laragon/www/real-rent-car-main/resources/js/pages/Admin/Settings/SeoSettings.vue)

### What it shows

For each page, the admin can now see the actual tag output that will be read by search engines and social platforms:

- `<title>`
- `meta description`
- `meta robots`
- canonical tag
- hreflang alternates
- `og:*`
- `twitter:*`

### Why it exists

Visual previews are helpful, but they are still approximations.

This section shows the real values that external platforms will parse from the page.

## Sidebar Access

File:

- [AppSidebar.vue](C:/laragon/www/real-rent-car-main/resources/js/components/AppSidebar.vue)

Current entries added:

- SEO Settings
- SEO Audit

Why:

- SEO should not be hidden behind Website Settings only
- tenants need direct access

## Translation Keys Added

Files:

- [site.php](C:/laragon/www/real-rent-car-main/lang/en/site.php)
- [site.php](C:/laragon/www/real-rent-car-main/lang/ar/site.php)
- [site.php](C:/laragon/www/real-rent-car-main/lang/ur/site.php)

Added keys:

- `dashboard.sidebar.admin.seo_settings`
- `dashboard.sidebar.admin.seo_audit`

## What Has Been Implemented

The following is implemented now:

- tenant SEO storage in `tenant_site_settings`
- localized SEO defaults
- per-page SEO settings
- dedicated SEO settings page
- dedicated SEO audit page
- SEO removed from general settings form
- direct sidebar access to SEO pages
- SEO previews for search / Open Graph / Twitter
- actual rendered meta-tag view
- canonical tags
- hreflang alternates
- `x-default`
- schema.org generation
- product schema for car page
- reservation schema for booking pages
- breadcrumb schema
- SEO audit exports in TXT / CSV / JSON
- noindex handling for booking operational pages

## What Has Not Been Implemented Yet

The following is not implemented yet:

- external social debugger links for Facebook / X / LinkedIn
- live fetch of the final rendered HTML from production and comparison against configured SEO
- image dimension validation for OG images
- per-page custom `robots` settings
- per-page custom `og:image`
- per-page custom Twitter card type
- blog/article SEO
- search results page SEO
- redirect manager
- broken-link scanner
- automatic slug suggestion/generation
- auto-fix actions in the audit page
- XML sitemap management UI
- robots.txt management UI
- Google Search Console integration
- keyword density / readability scoring like Yoast
- internal-linking suggestions

## Important Current Limitations

### 1. Previews are approximate

The Open Graph and Twitter previews are internal UI approximations.

They are useful, but final display on:

- WhatsApp
- Facebook
- LinkedIn
- X/Twitter

may still differ because each platform has its own cache and rendering rules.

### 2. Booking pages are intentionally not indexable

Checkout and confirmation pages are currently treated as operational pages.

They are not intended to rank in search results.

### 3. SEO Settings page still uses page-level defaults only

The system is not yet at the level of full CMS-grade per-model SEO management.

For example:

- cars have SEO fallback logic
- but there is not yet a separate admin editor for each individual car’s custom SEO fields in the database

### 4. No external verification layer yet

The current system validates format and structure internally, but it does not yet verify:

- what Facebook scraper actually sees
- what X scraper actually sees
- what Google actually cached

## Suggested Next Steps

Recommended next priorities:

1. Add social debugger helper links
2. Add sitemap and robots.txt management UI
3. Add per-car custom SEO overrides in admin
4. Add OG image validation and recommended dimensions
5. Add per-page custom robots setting
6. Add production-fetch verification for final tags

## Verification Notes

Implemented routes and PHP syntax were checked during development.

Frontend build verification was not documented here as a guaranteed completed step.

If this module is deployed to production, validate:

1. `SEO Settings` opens correctly
2. `SEO Audit` opens correctly
3. sidebar links are visible for users with `tenant-manage-settings`
4. public pages render expected meta tags
5. booking pages still render `noindex,nofollow`

## Summary

The SEO system is now a practical tenant-managed SEO module with:

- dedicated admin management
- dedicated audit reporting
- public-page meta rendering
- schema support
- multilingual alternates

It is not yet a full Yoast-style SEO platform, but it is already a usable and structured multi-tenant SEO foundation.
