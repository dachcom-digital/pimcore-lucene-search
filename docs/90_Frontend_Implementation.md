# Lucene Search FrontEnd
This guide will help you to implement a search page into your website in seconds.

### Optional: Create a Layout/Controller
> Note: This is only required if you're starting a project from scratch.

- Create a layout in `app\Resources\views\layout.html.twig`
- Add some markup to your layout:

```twig
<!DOCTYPE html>
<html lang="{{ app.request.locale }}">
    <head>
       {# your head data #}
    </head>
    <body>
        <main>
            {% block content %}
               {# your main content data #}
            {% endblock %}
        </main>
    </body>
</html>
```
- Create a controller, name it `DefaultController`
- Create a method, name it `searchAction(Request $request);`

### Setup Search Page
- Create a document, call it "search"
- Optional: In document settings, set controller to `DefaulController` and Method to `searchAction`.
- Create a view template (eg. `app\Resource\views\Default\search.html.twig`)
- Add some twig markup to the view:

```twig
{% extends 'layout.html.twig' %}

{# note: the "content" block must be available in your master layout, see optional config above. #}
{% block content %}
    {{ render(controller('LuceneSearchBundle:List:getResult')) }}
{% endblock %}
```

This will load the result template from `@LuceneSearch/Resources/views/List/result.html.twig`.
If you want to use your own custom templates to display the search results, place them inside
`app/Resources/LuceneSearchBundle/views/List/*.html.twig` (see symfony [documentation](https://symfony.com/doc/current/templating/overriding.html) for further details).

### Ajax AutoComplete
Use this snippet to allow ajax driven auto-complete search. you may want to use this [plugin](https://github.com/devbridge/jQuery-Autocomplete) to do the job.

1. Add some JS files (in your layout for example):

```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.devbridge-autocomplete/1.4.1/jquery.autocomplete.min.js"></script>
```

2. Add auto-complete to your project:

```javascript
$(function() {

    var $el = $('input.search-field'),
        language = $el.data('language'), //optional
        country = $el.data('country'),
        $categoryEl = $el.closest('form').find('select.categories'),
        categories = []; //optional

    $el.autocomplete({
        minChars: 3,
        triggerSelectOnValidInput: false,
        lookup: function(term, done) {
            //update on every lookup because user may have changed the dropdown selection.
            categories = $categoryEl.val(); //optional
            $.getJSON(
                '/lucence-search/auto-complete',
                {
                    q: term,
                    language : language,
                    country: country,
                    categories: categories
                },
                function(data) {
                    var result = { suggestions : [] };
                    if(data.length > 0) {
                        $.each(data, function(index, suggestion) {
                            result.suggestions.push( {value : suggestion });
                        });
                    }
                    done(result);
                });
        },
        onSelect: function(result) {
            $el.val(result.value);
            $el.parents('form').submit();
        }
    });
});
```

3. Place this html snippet on top of your layout for example:

```html
<form id="search" method="get" action="/en/search">

    <div class="row">

        <div class="col-xs-12 col-sm-6">
            <label for="search-field">{{ 'search'|trans }}</label>
            <input type="text" name="q" id="search-field" class="form-control input-lg search-field" data-country="AT" data-language="en" placeholder="{{ 'search'|trans }}">
        </div>

        <!-- optional, let user choose some categories (multiple) -->
        <div class="col-xs-12 col-sm-6">
            <label for="search-categories">{{ 'categories'|trans }}</label>
            <select name="categories[]" id="search-categories" class="categories form-control" multiple>
                {% for category in lucene_search_get_categories() %}
                    <option value="{{ category.id }}">{{ category.label}}</option>
                {% endfor %}
            </select>
        </div>
    </div>

    <input type="hidden" name="language" id="searchLanguage" value="en">
    <input type="hidden" name="country" id="searchCountry" value="AT">

</form>
```

4. Done. Now try to search something without hitting return.

> Don't forget to start your crawler before testing the auto-completer.
