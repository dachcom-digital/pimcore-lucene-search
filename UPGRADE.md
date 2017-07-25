# Upgrade Notes

#### Update from Version 1.5.x to Version 1.6.0
- category meta search implemented: restart the crawler to update your index.

#### Update from Version 1.4.x to Version 1.5.0
- if you're using the [Members Extension](https://github.com/dachcom-digital/pimcore-members), please be sure you're using the latest version (at least 1.5.0).

#### Update from Version 1.3.x to Version 1.4
- configuration: content crawl size added. check and update lucene configuration after updating.
- configuration: mime type restriction added. check and update lucene configuration after updating.
- new property added: `assigned_country`.

#### Update from Version 1.2.x to Version 1.3
- only works with pimcore build > 3987 because of document meta tags (see #23).

#### Update from Version 1.1.1 to Version 1.2
- If you're using a custom template, check your markup first. we changed the view params from ground up.
- page property `assignedLanguage` changed to `assigned_language`. Please update your database entries.
- because of the new `customMeta` field you need to start the crawler after updating.
- open the "Lucene Search" settings and update the Boost settings (default should be 1 for both).
