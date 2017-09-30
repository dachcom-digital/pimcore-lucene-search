# Categories
It's possible to activate a category based indexing / searching. 

### Configuration

```yaml
lucene_search:
    enabled: true
    categories: AppBundle\LuceneSearch\Services\Categories
```

You need a custom service for that which implements the `LuceneSearchBundle\Configuration\Categories\CategoriesInterface` interface.
So you're class may looks like this:

```php
<?php

namespace AppBundle\LuceneSearch\Services;

use LuceneSearchBundle\Configuration\Categories\CategoriesInterface;

class Categories implements CategoriesInterface {

    /**
     * @return array
     */
    public function getCategories() : array
    {
        //of course it's possible to get your categories from a storage.
        return [
            [ 'id' => 1, 'label' => 'Category 1'],
            [ 'id' => 2, 'label' => 'Category 2'],
        ];
    }

}
```

To inform the lucene search crawler about those categories we need to add another meta element. 
As you can see it's also possible to add multiple categories per document.

```html
{% if lucene_search_crawler_active() %}
    <meta name="lucene-search:categories" content="1,2,47">
{% endif %}
```

Congratulations, you're done. From now on the categories get stored into the lucene index. 

### Twig Extension
If you need the categories in your template, you could use the following snipped:

```html
{% for category in lucene_search_get_categories() %}
    Id: {{ category.id }}, Label: {{ category.label}}
{% endfor %}
```

### Templating
If you want to know how to implement the categories in frontend, checkout our [frontend implementation advice](90_Frontend_Implementation.md).
