# Elasticsearch for TYPO3

Highly and easy configurable elasticsearch adapter for TYPO3.

## Prerequisites
* Elasticsearch 7

## Known Problems
The use of staticfilecache with threads enabled and Elasticsearch->ProgressBodyMiddleware is not yet possible.

## Recommendations
Usage of [EXT:staticfilecache](https://github.com/lochmueller/staticfilecache) is strongly recommended as the page indexing happens on the fly when the middleware is being executed.
No code execution -> no performance issues.
Usage of [typo3_console](https://github.com/TYPO3-Console/TYPO3-Console) is strongly recommended for executing commands easily.

## Quickstart
1. Add two comments to your default fluid layout to mark the indexable contents: "\<!--TYPO3SEARCH_begin-->" and "\<!--TYPO3SEARCH_end-->". (Add those sections mutliple times if you wish to.)
2. Add the yaml configuration to your sites.
3. Run the command "elasticsearch:create-indices" to create the needed structure inside your elasticsearch
4. Run the command "elasticsearch:index-records" to index your records.
5. Create a TypeNum to the SearchController / searchAction or create a plugin on a noindex page to which you send an ajax request, as the searchAction only returns json with results.

You may implement your own plugin for search handling based on your needs.
The searchAction in this extension only provides a basic search (which already should fit most needs).

## Available commands
### elasticsearch:create-indices
Deletes existing indices and creates the indices you configured inside the yaml-files.

### elasticsearch:index-records
Indexes all records defined in your "tables"-section. Also deletes all records newly marked as "hidden" or "deleted", also deletes deleted or hidden pages from the index.

At the moment there is no command to add fields to an index afterwards. You will have to use "elasticsearch:create-indices". This may cause data loss and reindexing. 

## Configuration

This extension uses the site configuration of TYPO3 to configure indices, settings, fields and mappings to your instance.
This way multiple page trees can use different indices to work with.

### Overview of yaml values

Take care of the indentations and list types in yaml, look for the configuration example if something does not work as intended.

| Name               | Type         | Description                                                                                         |
| ------------------ | ------------ | ----------------------------------------------------------------------------------------------------|
| usePageMiddleware  | bool         | Use middleware page processing. Therefore pages are getting indexed for every uncached page request.|
| index              | string       | Name of the index inside elasticsearch. Default: "typo3"                                            |
| server             | array        | Elasticsearch server connection configuration                                                       |
| analyzers          | array        | Define your own analyzers                                                                           |
| mapping            | array (list) | Elasticsearch field definitions                                                                     |
| tables             | array        | Mapping of TYPO3-values to elasticsearch fields, indexer configuration                              |
| searchFields       | array        | List of fields which should be searched in                                                          |

### Hosts
You can use the [elasticsearch-php-api](https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/configuration.html#_extended_host_configuration) docs to see which host configuration options you can use.
```yaml
# Example configuration
# yoursite.yaml

# TYPO3 defaults
base: 'https://www.foo.com/'

# Elasticsearch configuration
elasticsearch:
  server:
    host: elastic.foo.com
    port: 9200
  indices:
    # define a default
    german: &default
      index: german
    # copy the default to english
    english:
      << : *default
      index: english
    # create something new
    products:
      index: products
```

### Elasticsearch fields

The overview for all field types and configuration options can also be seen inside the [elasticsearch docs](https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-types.html).
This section creates the fields which are created inside the elasticsearch index.

```yaml
elasticsearch:

  # https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping.html
  mapping:
    -
      name: title

      # see https://www.elastic.co/guide/en/elasticsearch/reference/current/mapping-params.html
      parameters:
        type: keyword
        store: true
    -
      name: teaser
      parameters:
        type: text
        index: false
        store: true
    -
      name: content
      parameters:
        type: text
        store: true
        copy_to: escaped_content
    -
      name: escaped_content
      parameters:
        type: text
        store: true
        analyzer: html_analyzer
``` 


Of course the site/language combination needs to know it's index name to use

```yaml
languages:
  -
    title: 'German website'
    elasticsearch:
      index: de-de
``` 


## Analyzers
You can specify and use your own analyzers as well. Here is an example of an analyzer with stripped html-chars and a lowercase filter:
```yaml
elasticsearch:
  indices:
    german:
      
      # see https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-custom-analyzer.html
      analyzers:
        html_analyzer:
          type: custom
          tokenizer: standard
          char_filter:
            - html_strip
          filter:
            - lowercase
``` 

## Indexing pages

To map the database-fields to the matching elasticsearch-fields you can simply create another mapping inside of your yaml-file.
All indices inside of "tables" are table names of your TYPO3 project (pages, tt_address, tx_myext_domain_model_name):

```yaml
elasticsearch:
  indices:
    german:
      usePageMiddleware: true
      tables:
        pages:
          mapping:
            
            # content and url are predefined variables in php
            # elasticValue: typo3Value
            content: content
            url: url
            title: title
```

Pages are getting indexed on uncached page-load via middleware. This means after changing a page the changes are synchronized immediately to elasticsearch.

#### IMPORTANT: There are two page-fields which are filled automatically:
* url
* content

You NEED to define those two fields. The url contains the url-path, the content contains the complete rendered html-content between the two TYPO3 search-tags: "\<!--TYPO3SEARCH_begin-->" and "\<!--TYPO3SEARCH_end-->".

So in order to index real content you have to define these two tags inside of your page layout.
If "usePageMiddleware" is set to false, the middleware does not index pages. You can also index pages via the GenericTableIndexer (see below).

### Ignore pages

Pages flagged deleted, hidden, no_follow, no_index or with fe-group-permission are not getting indexed.
Pages with route arguments or arguments in general are not getting indexed either.

### Page deletion

All hidden, deleted, noindex, nofollow and fe_group pages are getting deleted every "elasticsearch:index-records" run.
If you need to reindex your page simply hard-reload it.

## Indexing custom tables (tx_myext_domain_model_ext)

It is possible to simply index all tables you have inside of your database. Take a look at this example:

```yaml
elasticsearch:
  indices:
    german:
      tables:
        tx_myextension_domain_model_table:
          indexClass: Pluswerk\Elasticsearch\Indexer\GenericTableIndexer
          uriBuilderConfig:
            extensionName: MyExtension
            pluginName: Extension
            controllerName: Extension
            actionName: detail
            
            # argument which name gets resolved, or the entity name - e.g. it is a event detail page, so most likely the argument is "event"
            argumentName: table
            
            # detail page uid
            pageUid: 123
          mapping:
            content: text
            teaser: teaser
            title: title
            
            # you need to provide "url" in order to automatically generate urls
            url: placeholder

```

If no indexClass is given, the command does not index this table (e.g. needed if you use the pages middleware).

You can also define your own indexers, they have to extend the class "\Pluswerk\Elasticsearch\Indexer\AbstractElasticIndexer" and implement the process()-method.
Just take a look at the GenericTableIndexer itself and adjust the logic to your needs.

You can also add urls to your entries. The field "url" is predestined and should be used. You can provide a valid "uriBuilderConfig" as seen above to automatically generate URLs to your detail pages.

# Complete configuration example

```yaml
elasticsearch:
  server:
    - '%env(HOST_ELASTIC)%'
  # mapping schema for all indices
  mapping:
    -
      name: title
      parameters:
        type: keyword
        index: true
        store: true
    -
      name: type
      parameters:
        type: keyword
        index: true
        store: true
    -
      name: contentExact
      parameters:
        type: text
        store: true
        index: false
        copy_to: content
    -
      name: content
      parameters:
        type: text
        store: true
        index: true
        analyzer: html_analyzer
    -
      name: url
      parameters:
        type: keyword
        index: false
        store: true

  indices:
    german: &default
      index: german
      searchFields:
        - content
        - title
      analyzers:
        html_analyzer:
          type: custom
          tokenizer: standard
          char_filter:
            - html_strip
          filter:
            - lowercase
      usePageMiddleware: true
      tables:
        pages:
          mapping:
            # content and url are predefined variables in php
            # elasticValue: typo3Value
            content: content
            url: url
            title: title
        rss-feed-news:
          indexClass: Pluswerk\Elasticsearch\Indexer\RssFeedIndexer
          config:
            uri: <RSS FEED URI>
          mapping:
            title: title
            publicationDate: pubDate
            url: link
            contentExact: description
            type: type
            uid: uid
    english:
      << : *default
      index: english
      tables:
        pages:
          mapping:
            content: content
            url: url
            title: title
        tx_myextension_domain_model_table:
          indexClass: Pluswerk\Elasticsearch\Indexer\GenericTableIndexer
          uriBuilderConfig:
            extensionName: MyExtension
            pluginName: Extension
            controllerName: Extension
            actionName: detail
            # argument which name gets resolved, or the entity name - e.g. it is a event detail page, so most likely the argument is "event"
            argumentName: table
            # detail page uid
            pageUid: 123
          mapping:
            content: text
            teaser: teaser
            title: title
            url: url

```

### Example of embedding an ajax search endpoint

With this configuration, a request to the search-engine could look as follows (also for assembling with javascript):

```
curl https://mytypo3website.com/?type=12345&q=mysearchterm
```

```typo3_typoscript
elasticSearch = PAGE
elasticSearch {
  typeNum = 12345
  config {
    disableAllHeaderCode = 1
    debug = 0
  }
  10 = USER
  10 {
    userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
    pluginName = Elasticsearch
    extensionName = Elasticsearch
    controller = Search
    vendorName = Pluswerk
  }
}
```

# UID and Type
The fields are used internally and will transform to

 - uid = tablename + uid 
   - is removed after used as _id (concatenated tablename+uid).
 - type = tablename + type
   - if empty type = tablename 

# Symbiosis
Witht he use of staticfilecache extension and their BoostQueue you can enable boost-time site indexing with a Listener

```yaml
services:
  Pluswerk\Elasticsearch\EventListener\BuildClientEventListener:
    tags:
      - name: event.listener
        identifier: 'Pluswerk\Elasticsearch\EventListener\BuildClientEventListener'
        event: SFC\Staticfilecache\Event\BuildClientEvent
```

# Synonyms
[indextime or searchtime?](https://www.elastic.co/guide/en/elasticsearch/guide/1.x/synonyms-expand-or-contract.html#synonyms-expansion)

[more about](https://www.elastic.co/guide/en/elasticsearch/reference/current/search-analyzer.html)

### The self-toggle
Read more about the toggler, if you do not know what it does, leave it disabled to get mostly what you want.

Explicit mappings match any token sequence on the LHS of "=>"
and replace with all alternatives on the RHS.  These types of mappings
ignore the expand parameter in the schema.

["sea biscuit, sea biscit => seabiscuit" vs "sea biscuit, sea biscit, seabiscuit"](https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-synonym-tokenfilter.html)

#Dumb session
This holds the workaround to do multiple languages in one CLI command. The identitymap has to forget about the previous language's object so the datamapper works correctly with new relations


    Before: Model A (en), Submodel B (en)
    ... both translated
    Model A (de), Submodel B (en)
    
    Now correctly
    Model A (de), Submodel B (de)

# where to search?

You can set the PUBLIC_HOST_ELASTIC environment Variable, it will be available in fluid {{data.elasticsearch.url}} (if you want another variable, change in TypoScript).
 - fast featurerich full access
 - perfect for public data
 - perfect for use in vue
 - send your search objects in the request body

If you do not set PUBLIC_HOST_ELASTIC environment Variablethe URI to the search Controller is built.
 - fully bootstrapped TYPO3 elastic proxy
 - you can add more complex queries with usergroups taken into account
 - simply use the q Parameter "?q=fox" to search for fox
