# Upgrade Notes

#### Update from Version 2.1.0 to Version 2.1.1
- Implemented [PackageVersionTrait](https://github.com/pimcore/pimcore/blob/master/lib/Extension/Bundle/Traits/PackageVersionTrait.php)
- Various bug fixes ([Milestone](https://github.com/dachcom-digital/pimcore-lucene-search/milestone/5?closed=1))

#### Update from Version 2.0.x to Version 2.1.0
- **[REMOVED FEATURE]**: The SiteMap Feature has been removed. Please remove the `lucene_search.sitemap.render` config element **before** updating!
- **[CRITICAL BUGFIX]**: There was a wrong path assignment for the tmp persistence manager. Please delete the `/var/tmp/ls-crawler-tmp` folder immediately.

#### Update from Version 2.0.x to Version 2.0.2
- **[NEW FEATURE]**: [Query/Hash Url Filter](docs/00_Configuration_Values.md) implemented

#### Update from Version 1.x to Version 2.0.0
TBD