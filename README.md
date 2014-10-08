Graph app
=========

Prototype app providing endpoints for recommending tags given a text and recommending articles based on clustered reads and a those-who-read-this-also-read algorithm.

####Entity detection
`POST /detectEntities`

Expects the article text (title, lead-in, body...) as request body and parses the text looking for matches in the tags in the graph.

Also identifies named entities if two (or more) consecutive starts with an uppercase letter, or if a word starts with two or more uppercase-letters.

Returns a JSON response with two lists, in this format;

```json
{
    "knownEntities": [
        "Barack Obama",
        "USA",
        "Irak"
    ],
    "unknownEntities": [
        "ISIS",
        "Chuck Hagel"
    ]
}
```

####Entity suggestion
`GET /suggestEntities?topic[]=Barack%20Obama&topic[]=USA&topic[]=Irak` 

Looks at other articles with the same entities and returns a list of popular tags for articles with the provided set of tags.

Returns a list of tags with meta information like entity id, type of tag and so on.

```json
[
    {
        "id": 123,
        "name": "ISIS",
        "remoteType": "tag",    // Reference to entity origin context
        "type": "Organization", // Type of tag
        "typeId": 5,            // Id of tag type (in DrPublish)
        "mentions": 45          // Number of times used in the explored articles
    },
    ...
]
```

####Article recommendation
`GET /recommended/{articleId}`

Based on user read-clusters, the algorithm looks at people who read this article and what other articles the user read. The articles is then sorted on how many people read the article and sorted on that in descending order.

If users were signed in with SPiD, the data would be much more useful than the IP we currently used, and if articles the user has read was filtered out it would probably be of value to the user.

Returns list with id and title for the suggested articles;

```json
[
    { 
        "id": 23311324, 
        "title": "Statsbudsjettet: Siv kutter skatt for de aller rikeste"
    },
    ...
]
```
