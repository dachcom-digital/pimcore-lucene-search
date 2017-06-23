# Pimcore 5 Lucene Search

> This Lucene Search Repo is for pimcore 5 only and under heavy development.

![lucenesearch crawler](https://cloud.githubusercontent.com/assets/700119/25579028/7da66f40-2e74-11e7-8da5-988d61feb2e2.jpg)

### Requirements
* Pimcore 5

### Configuration

To enable LuceneSearch, add those lines to your `AppBundle/Resources/config/pimcore/config.yml`:
    
```yaml
lucene_search:
    enabled: false
```

You need to add add the config parameter to your config.yml to override the default values. 
Execute this command to get some information about all the config elements of LuceneSearch:

```bash
# configuration about all config parameters
$ bin/console config:dump-reference LuceneSearchBundle

# configuration info about the "fuzzy_search" parameter
$ bin/console config:dump-reference LuceneSearchBundle fuzzy_search
```

## Copyright and license
Copyright: [DACHCOM.DIGITAL](http://dachcom-digital.ch)  
For licensing details please visit [LICENSE.md](LICENSE.md)  

## Upgrade Info
Before updating, please [check our upgrade notes!](UPGRADE.md)
