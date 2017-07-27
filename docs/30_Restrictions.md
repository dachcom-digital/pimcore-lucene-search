# Restrictions
If you want a seamless integration of protected document crawling, install our [member](https://github.com/dachcom-digital/pimcore-members) plugin.

### Documents
Each document needs a meta tag in the head section. the crawler extract and stores the usergroup id(s) from that meta property. To allow the crawler to follow all the restricted documents, you need to configure the crawler authentication settings. 

> If you're using the Members Bundle this meta property gets assigned automatically.

**Meta Property Example**
```html
<meta name="m:groups" content="4">
```

If the document is restricted to a specific user group, the meta `content` contains its id. Otherwise, the meta property needs to be filled with a `default` value.

### Assets
Since Assets does not have a html view, you need to catch an event (`lucene_search.task.parser.asset_restriction`). 
 > If you're using the Members Bundle this event is already implemented.

## Asset Language restriction
Because Assets does not have any language hierarchy, you need to add a property called `assigned_language`. This Property will be installed during the install process of LuceneSearch.
If you add some additional language afterwards, you need to add this language to the property. if you do not set any information at all, the asset will be found in any language context.

## Asset Country restriction
Because Assets does not have any country hierarchy, you need to add a property called `assigned_country`. This Property will be installed during the install process of LuceneSearch.
If you add some additional countries afterwards, you need to add this country to the property. if you do not set any information at all, the asset will be found in any country context.

## Events

| Name | Class | Setter |
|---------------------|-------------|-------------------------------|
| `lucene_search.task.parser.asset_restriction` | Event\AssetResourceRestrictionEvent | setRestrictions, setAsset |
| `lucene_search.frontend.restriction_context` | Event\RestrictionContextEvent | setAllowedRestrictionGroups |