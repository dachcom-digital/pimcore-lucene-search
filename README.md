# Pimcore Lucene Search

Just download and install it into your plugin folder.

### Requirements
* Pimcore 4.0

### Features
* Maintenance driven indexing
* Auto Complete
* Restricted Documents & Usergroups ([member](https://github.com/dachcom-digital/pimcore-members) plugin recommended but not required)
* Authenticated Crawling

### Document Restrictions
If you want a seamless integration of protected document crawling, install our [member](https://github.com/dachcom-digital/pimcore-members) plugin.

#### How does the document restriction work?
Each document needs a meta tag in the head section. the crawler extract and stores the usergroup id(s) from that meta property. 
To allow the crawler to follow all the restricted documents, you need to configure the crawler authentication settings. 

**Meta Property Example**

```html
<meta name="m:groups" content="4">
```

If the document is restricted to a specific usergroup, the meta `content` contains its id. Otherwise, the meta property needs to be filled with a `default` value.