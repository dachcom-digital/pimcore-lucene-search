# Pimcore 5 Lucene Search

> This Lucene Search Repo is for pimcore 5 only and under heavy development.

![lucenesearch crawler](https://cloud.githubusercontent.com/assets/700119/25579028/7da66f40-2e74-11e7-8da5-988d61feb2e2.jpg)

### Requirements
* Pimcore 5

### Installation  
1. Add code below to your `composer.json`    
2. Activate & install it through the ExtensionManager

```json
"require" : {
    "dachcom-digital/lucene-search" : "2.0.0",
}
```

### Configuration
To enable LuceneSearch, add those lines to your `AppBundle/Resources/config/pimcore/config.yml`:
    
```yaml
lucene_search:
    enabled: true
```

You need to add the config parameter to your config.yml to override the default values. 
Execute this command to get some information about all the config elements of LuceneSearch:

```bash
# configuration about all config parameters
$ bin/console config:dump-reference LuceneSearchBundle

# configuration info about the "fuzzy_search" parameter
$ bin/console config:dump-reference LuceneSearchBundle fuzzy_search
```

### Features
* Maintenance driven indexing
* Auto Complete
* Restricted Documents & Usergroups ([member](https://github.com/dachcom-digital/pimcore-members) plugin recommended but not required)
* Authenticated Crawling

### Usage

**Default**  
The crawler Engine will start automatically every night by default. Please check that the pimcore default maintenance script is properly installed.

**Command Line Command**  
If you want to start the crawler manually, use this command:

```
$ php bin/console lucenesearch crawl -f -v
```

| command | short command | type | description |
|:---|:---|:---|:---|
| ```force``` | `-f` | force crawler start | sometimes the crawler stuck because of a critical error mostly triggered because of wrong configuration. use this command to force a restart |
| ```verbose``` | `-v` | show some logs | good for debugging. you'll get some additional information about filtered and forbidden links while crawling. |


## Frontend Implementation
click [here](docs/00_Views.md) to get a step by step walkthrough to implement lucene search into your website.

### Logs
You'll find some logs from the last crawl in your backend (at the bottom on the LuceneSearch settings page). Of course you'll also find some logs in your `var/logs` folder.
**Note:** please enable the debug mode in pimcore settings to get all types of logs.

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

## Asset Language restriction
Because Assets does not have any language hierarchy, you need to add a property called `assigned_language`. This Property will be installed during the install process of LuceneSearch.
If you add some additional language afterwards, you need to add this language to the property. if you do not set any information at all, the asset will be found in any language context.

## Asset Country restriction
Because Assets does not have any country hierarchy, you need to add a property called `assigned_country`. This Property will be installed during the install process of LuceneSearch.
If you add some additional countries afterwards, you need to add this country to the property. if you do not set any information at all, the asset will be found in any country context.

## Custom Meta Content
In some cases you need to add some content or keywords to improve the search accuracy. 
But it's not meant for the public crawlers like Google. LuceneSearch uses a custom meta property called `lucene-search:meta`.
This Element should be visible while crawling only.

**Custom Meta in Documents**  
In *Document* => *Settings* go to *Meta Data* and add a new field:

```html
<meta name="lucene-search:meta" content="your content">
```

**Custom Meta in Objects**  
Because Object may have some front-end capability (a news detail page for example), you have to integrate the custom meta field by yourself.

**Example:**

```php
if( \LuceneSearch\Tool\Request::isLuceneSearchCrawler() )
{
    $this->view->headMeta()->setName( 'lucene-search:meta', $product->getInternalSearchText( $lang ) );
}
```

**Custom Meta in Assets**  
TBD

## Copyright and license
Copyright: [DACHCOM.DIGITAL](http://dachcom-digital.ch)  
For licensing details please visit [LICENSE.md](LICENSE.md)  

## Upgrade Info
Before updating, please [check our upgrade notes!](UPGRADE.md)