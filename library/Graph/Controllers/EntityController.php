<?php

namespace Graph\Controllers;

use Silex\Application;

use Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

use Everyman\Neo4j\Cypher\Query;

class EntityController {
    protected function isBlacklisted($string) {
        return in_array($string, [
            'vg'
        ]);
    }

    public function detectEntitiesAction(Request $req, Application $app) {
        $cacheKey = 'graph::entityExtraction::entitiesData';

        // Batching options for neo4j
        $limit = 500;
        $offset = 0;

        $body = $req->getContent();

        if (!strlen($body)) {
            return $app->json([], 200);
        }

        $entityData = $app['memcache']->get($cacheKey);

        if (!$entityData) {
            $entities           = [];
            $singleWordEntities = [];
            $multiWordEntities  = [];

            // Get entities
            do {
                $entityQuery = new Query(
                    $app['neo4j'],
                    'MATCH (topic:topic {remoteType: "tag"})
                    RETURN
                        labels(topic) as labels,
                        topic.name as name,
                        topic.topicId as topicId,
                        topic.tagTypeId as tagTypeId
                    SKIP {skip}
                    LIMIT {limit}',
                    [
                        'limit' => $limit,
                        'skip'  => $offset
                    ]
                );

                $resultSet = $entityQuery->getResultSet();

                foreach ($resultSet as $row) {
                    $key = trim(strtolower($row['name']));

                    // We don't need them empty strings..
                    if (!$key) {
                        continue;
                    }

                    $singleWordEntity = stripos($key, ' ') === false;

                    // Skip if we've got this already
                    if ($singleWordEntity && in_array($key, $singleWordEntities)) {
                        continue;
                    } else if (!$singleWordEntity && in_array($key, $multiWordEntities)) {
                        continue;
                    }

                    $type = '';

                    foreach ($row['labels'] as $label) {
                        if ($label == 'topic') {
                            continue;
                        }

                        $type = $label;
                        break;
                    }

                    $entities[$key] = [
                        'type'   => $type,
                        'typeId' => $row['tagTypeId'],
                        'id'     => (int) $row['topicId'],
                        'name'   => $row['name']
                    ];

                    if ($singleWordEntity) {
                        $singleWordEntities[] = $key;
                    } else {
                        $multiWordEntities[] = $key;
                    }
                }

                $offset += $limit;
            } while (count($resultSet) === $limit);

            $app['memcache']->set($cacheKey, [
                'multi'  => $multiWordEntities,
                'single' => $singleWordEntities,
                'full'   => $entities
            ], 0, 300);
        } else {
            $multiWordEntities  = $entityData['multi'];
            $singleWordEntities = $entityData['single'];
            $entities           = $entityData['full'];
        }

        // Simple body text wash
        $lowecaseBody = mb_convert_case($body, MB_CASE_LOWER);

        $textWords = str_replace("\n", ' ', $lowecaseBody);
        $textWords = explode(' ', $textWords);

        $textWords = array_unique(array_map(function($string) {
            return trim($string, ' .,!:;-–');
        }, $textWords));

        // Intersect words in text with the single word entities
        $entitiesInText = [];

        foreach (array_intersect($textWords, $singleWordEntities) as $entity) {
            if ($this->isBlacklisted($entity)) {
                continue;
            }

            $entitiesInText[$entity] = $entity;
        }

        // Search the text for multi word entities
        foreach ($multiWordEntities as $entity) {
            if ($this->isBlacklisted($entity)) {
                continue;
            }

            if (stripos($lowecaseBody, $entity) !== false) {
                $entitiesInText[$entity] = $entity;
            }
        }

        // Get the full entities for the ones that was found in text
        $entitiesInText = array_map(function($entity) use ($entities) {
            return $entities[$entity];
        }, $entitiesInText);

        // Find named entities
        preg_match_all('/([A-ZÆØÅ][a-zæøå]+(?=\s[A-ZÆØÅ])(?:\s[A-ZÆØÅ][a-zæøå]+)+)|[\n ]+([A-ZÆØÅ][A-ZÆØÅ0-9]{2,})/u', $body, $matchedEntities);

        // Make shure the indexes are there before merging
        if (!isset($matchedEntities[1])) { $matchedEntities[1] = []; }
        if (!isset($matchedEntities[2])) { $matchedEntities[2] = []; }

        // Merge resultset containing consecutive capitalized words and uppercase words
        $matchedEntities = array_filter(array_merge($matchedEntities[1], $matchedEntities[2]));
        $matchedEntities = array_unique($matchedEntities);

        $unknownEntities = [];

        // Filter out the ones we have already before adding to list of unknown entities
        foreach ($matchedEntities as $entity) {
            $key = mb_convert_case($entity, MB_CASE_LOWER);

            if (isset($entitiesInText[$key]) || in_array($entity, $unknownEntities)) {
                continue;
            }

            if ($this->isBlacklisted($key)) {
                continue;
            }

            $unknownEntities[] = $entity;
        }

        return $app->json([
            'knownEntities' => array_values($entitiesInText),
            'unknownEntities' => $unknownEntities
        ], 200);
    }

    public function suggestEntitiesAction(Request $req, Application $app) {
        $limit     = 10;
        $topics    = isset($_GET['topic']) ? $_GET['topic'] : [];

        if (isset($_GET['limit']) && is_numeric($_GET['limit']) && $_GET['limit'] < 100) {
            $limit = (int) $_GET['limit'];
        }

        $topics = is_string($topics) ? explode(',', $topics) : $topics;

        $result = [];

        // Filtering on topics
        if (count($topics)) {
            $recommended = new Query(
                $app['neo4j'],
                'MATCH
                    (n:topic)-[:listed]->(a:article)<-[:listed]-(t:topic)
                WHERE n.name IN {topics}
                RETURN
                    t.topicId as topicId,
                    t.name as name,
                    t.remoteType as remoteType,
                    t.tagTypeId as tagTypeId,
                    labels(t) as labels,
                    count(1) as mentions
                ORDER BY mentions DESC
                LIMIT {limit}',
                [
                    'limit'     => $limit,
                    'topics'    => $topics
                ]
            );

            foreach ($recommended->getResultSet() as $row) {
                $type = '';

                foreach ($row['labels'] as $label) {
                    if ($label == 'topic') {
                        continue;
                    }

                    $type = $label;
                    break;
                }

                $result[] = [
                    'id'         => $row['topicId'],
                    'name'       => $row['name'],
                    'remoteType' => $row['remoteType'],
                    'type'       => $type,
                    'typeId'     => $row['tagTypeId'],
                    'mentions'   => $row['mentions'],
                ];
            }
        }

        return $app->json($result, 200, [
            'Cache-Control' => 'max-age=300'
        ]);
    }
}
