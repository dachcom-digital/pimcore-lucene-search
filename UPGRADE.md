# Upgrade Notes

#### Update from Version 2.1.1 to Version 2.1.2
- Availability Flag implemented.
- DocumentModifier implemented. See [Docs](https://github.com/dachcom-digital/pimcore-lucene-search/blob/master/docs/60_Index_Manipulation.md).
- Various Clean-Ups and try/catch improvements.
- Do not index pages with other status codes than 200.
- [Milestone](https://github.com/dachcom-digital/pimcore-lucene-search/milestone/7?closed=1)

#### Update from Version 2.1.0 to Version 2.1.1
- Implemented [PackageVersionTrait](https://github.com/pimcore/pimcore/blob/master/lib/Extension/Bundle/Traits/PackageVersionTrait.php).
- [Milestone](https://github.com/dachcom-digital/pimcore-lucene-search/milestone/5?closed=1)

#### Update from Version 2.0.x to Version 2.1.0
- **[REMOVED FEATURE]**: The SiteMap Feature has been removed. Please remove the `lucene_search.sitemap.render` config element **before** updating!
- **[CRITICAL BUGFIX]**: There was a wrong path assignment for the tmp persistence manager. Please delete the `/var/tmp/ls-crawler-tmp` folder immediately.

#### Update from Version 2.0.x to Version 2.0.2
- **[NEW FEATURE]**: [Query/Hash Url Filter](docs/00_Configuration_Values.md) implemented.

#### Update from Version 1.x to Version 2.0.0
TBD