# Pimcore 5 Lucene Search

> This Lucene Search Repo is for Pimcore5 only.

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

# configuration info about the "fuzzy_search_results" parameter
$ bin/console config:dump-reference LuceneSearchBundle fuzzy_search_results
```

We also added a [detailed documentation](docs/00_Configuration_Values.md) about all possible config values.

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
$ php bin/console lucenesearch:crawl -f -v
```

| command | short command | type | description |
|:---|:---|:---|:---|
| ```force``` | `-f` | force crawler start | sometimes the crawler stuck because of a critical error mostly triggered because of wrong configuration. use this command to force a restart |
| ```verbose``` | `-v` | show some logs | good for debugging. you'll get some additional information about filtered and forbidden links while crawling. |

## Logs
You'll find some logs from the last crawl in your backend (at the bottom on the LuceneSearch settings page). Of course you'll also find some logs in your `var/logs` folder.
**Note:** please enable the debug mode in pimcore settings to get all types of logs.

## Categories
[Click here](docs/20_Categories.md) to learn more about category based crawling / searching.

## Restrictions
[Click here](docs/30_Restrictions.md) to learn more about restricted crawling / indexing.

## Custom Meta Content
[Click here](docs/40_Meta.md) to learn more about crawling / searching custom meta.

## Frontend Implementation
[Click here](docs/90_Frontend_Implementation.md) to get a step by step walkthrough to implement lucene search into your website.

## Copyright and license
Copyright: [DACHCOM.DIGITAL](http://dachcom-digital.ch)  
For licensing details please visit [LICENSE.md](LICENSE.md)  

## Upgrade Info
Before updating, please [check our upgrade notes!](UPGRADE.md)